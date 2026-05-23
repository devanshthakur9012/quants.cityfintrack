<?php
// FILE: app/Http/Controllers/EventController.php

namespace App\Http\Controllers;

use App\Models\CoursePaymentGateway;
use App\Models\Event;
use App\Models\EventBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api as RazorpayApi;

class EventController extends Controller
{
    public $activeTemplate;

    public function __construct()
    {
        $this->activeTemplate = activeTemplate();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LISTING
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $pageTitle = 'Events';

        $filterType   = $request->input('type')   ?: null;
        $filterCity   = $request->input('city')   ?: null;
        $filterSearch = trim($request->input('search', '')) ?: null;

        $upcomingEvents = Event::whereIn('status', ['upcoming','ongoing'])
            ->with(['galleryItems','faqs','speakers.employeeProfile'])
            ->when($filterType,   fn($q) => $q->where('type', $filterType))
            ->when($filterCity,   fn($q) => $q->where('city', $filterCity))
            ->when($filterSearch, fn($q) => $q->where('title', 'like', '%'.$filterSearch.'%'))
            ->orderBy('sort_order')
            ->orderByDesc('is_featured')
            ->get();

        $pastEvents = Event::where('status','past')
            ->when($filterType,   fn($q) => $q->where('type', $filterType))
            ->when($filterCity,   fn($q) => $q->where('city', $filterCity))
            ->when($filterSearch, fn($q) => $q->where('title', 'like', '%'.$filterSearch.'%'))
            ->orderBy('sort_order')
            ->orderByDesc('event_date')
            ->get();

        return view($this->activeTemplate.'events', compact(
            'pageTitle','upcomingEvents','pastEvents'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DETAIL
    // ─────────────────────────────────────────────────────────────────────────
    public function detail(string $slug)
    {
        $event = Event::with([
                'galleryItems' => fn($q) => $q->orderBy('sort_order'),
                'faqs'         => fn($q) => $q->where('status',1)->orderBy('sort_order'),
                'speakers.employeeProfile',
            ])
            ->whereIn('status',['upcoming','ongoing','past'])
            ->where('slug', $slug)
            ->firstOrFail();

        $pageTitle  = $event->title;
        $isBooked   = false;
        $userEmail  = null;
        $user       = Auth::guard('web')->user();

        if ($user) {
            $userEmail = $user->email;
            $isBooked  = $event->isBookedBy($user->email);
        }

        $gateway = CoursePaymentGateway::where('status',1)->first();

        $relatedEvents = Event::where('id','!=',$event->id)
            ->whereIn('status',['upcoming','ongoing','past'])
            ->where(fn($q) => $q->where('badge',$event->badge)->orWhere('city',$event->city))
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        return view($this->activeTemplate.'event-detail', compact(
            'pageTitle','event','isBooked','userEmail','user','gateway','relatedEvents'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INITIATE BOOKING (popup form submit)
    // ─────────────────────────────────────────────────────────────────────────
    public function initiateBooking(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|max:100',
            'phone'   => 'nullable|string|max:20',
            'city'    => 'nullable|string|max:50',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false,'message'=>$validator->errors()->first()], 422);
        }

        if (!$event->canBook()) {
            return response()->json(['success'=>false,'message'=>'Bookings are currently closed for this event.'], 422);
        }

        if ($event->isBookedBy($request->email)) {
            return response()->json(['success'=>false,'message'=>'You have already registered for this event.'], 422);
        }

        if ($event->total_seats !== null && $event->seats_left <= 0) {
            return response()->json(['success'=>false,'message'=>'Sorry, this event is full.'], 422);
        }

        $userId = Auth::guard('web')->id();

        if ($event->type === 'free') {
            return $this->confirmFree($event, $request, $userId);
        }

        // Paid — Razorpay
        $gateway = CoursePaymentGateway::where('status',1)->first();
        if (!$gateway) {
            return response()->json(['success'=>false,'message'=>'Payment gateway not configured.'], 500);
        }

        try {
            $booking = EventBooking::create([
                'event_id'       => $event->id,
                'user_id'        => $userId,
                'name'           => $request->name,
                'email'          => $request->email,
                'phone'          => $request->phone,
                'city'           => $request->city,
                'message'        => $request->message,
                'payment_type'   => 'paid',
                'amount'         => $event->price,
                'payment_status' => 'pending',
                'order_number'   => EventBooking::generateOrderNumber(),
                'status'         => 'confirmed',
            ]);

            $razorpay = $this->getRazorpay($gateway);
            $rpOrder  = $razorpay->order->create([
                'receipt'  => $booking->order_number,
                'amount'   => (int) ($event->price * 100),
                'currency' => 'INR',
                'notes'    => ['event_id'=>$event->id,'booking_id'=>$booking->id],
            ]);

            $booking->update(['gateway_order_id'=>$rpOrder->id]);

            return response()->json([
                'success'     => true,
                'key'         => $gateway->getCredential('key_id'),
                'amount'      => (int) ($event->price * 100),
                'currency'    => 'INR',
                'order_id'    => $rpOrder->id,
                'booking_id'  => $booking->id,
                'event_name'  => $event->title,
                'user_name'   => $request->name,
                'user_email'  => $request->email,
                'user_phone'  => $request->phone ?? '',
            ]);
        } catch (\Exception $e) {
            Log::error('Event Razorpay: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Could not initiate payment.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VERIFY PAYMENT
    // ─────────────────────────────────────────────────────────────────────────
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
            'booking_id'          => 'required|integer',
        ]);

        $booking = EventBooking::with('event')->findOrFail($request->booking_id);
        $gateway = CoursePaymentGateway::where('status',1)->first();

        try {
            $razorpay = $this->getRazorpay($gateway);
            $razorpay->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ]);

            $booking->update([
                'payment_status'     => 'paid',
                'gateway_payment_id' => $request->razorpay_payment_id,
                'gateway_signature'  => $request->razorpay_signature,
                'gateway_response'   => json_encode($request->all()),
                'paid_at'            => now(),
                'status'             => 'confirmed',
            ]);

            $booking->event->increment('total_booked');

            return response()->json([
                'success' => true,
                'message' => 'Registration confirmed! You will receive a confirmation email shortly.',
            ]);
        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            $booking->update(['payment_status'=>'failed']);
            return response()->json(['success'=>false,'message'=>'Payment verification failed.'], 422);
        } catch (\Exception $e) {
            Log::error('Event payment verify: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Something went wrong.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE
    // ─────────────────────────────────────────────────────────────────────────
    private function confirmFree(Event $event, Request $request, ?int $userId)
    {
        EventBooking::create([
            'event_id'       => $event->id,
            'user_id'        => $userId,
            'name'           => $request->name,
            'email'          => $request->email,
            'phone'          => $request->phone,
            'city'           => $request->city,
            'message'        => $request->message,
            'payment_type'   => 'free',
            'amount'         => 0,
            'payment_status' => 'free',
            'order_number'   => EventBooking::generateOrderNumber(),
            'status'         => 'confirmed',
        ]);

        $event->increment('total_booked');

        return response()->json([
            'success' => true,
            'message' => 'Registration confirmed! You will receive a confirmation email shortly.',
        ]);
    }

    private function getRazorpay(CoursePaymentGateway $gateway): RazorpayApi
    {
        $keyId     = $gateway->getCredential('key_id');
        $keySecret = $gateway->getCredential('key_secret');
        if (!$keyId || !$keySecret) throw new \Exception('Razorpay credentials not configured.');
        return new RazorpayApi($keyId, $keySecret);
    }
}
