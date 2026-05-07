<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SymbolList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SymbolListController extends Controller
{
    public function index()
    {
        $pageTitle = 'Symbol Management';
        $symbols   = SymbolList::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.symbol-list.index', compact('pageTitle', 'symbols'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'underlying' => 'required|string|max:255',
            'symbol'     => 'required|string|max:255|unique:symbol_lists,symbol',
        ]);

        try {
            SymbolList::create([
                'underlying' => strtoupper(trim($request->underlying)),
                'symbol'     => strtoupper(trim($request->symbol)),
            ]);

            $notify[] = ['success', 'Symbol added successfully!'];
            return redirect()->route('admin.symbol-list.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Symbol Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error adding symbol: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, $id)
    {
        $symbol = SymbolList::findOrFail($id);

        $request->validate([
            'underlying' => 'required|string|max:255',
            'symbol'     => 'required|string|max:255|unique:symbol_lists,symbol,' . $id,
        ]);

        try {
            $symbol->update([
                'underlying' => strtoupper(trim($request->underlying)),
                'symbol'     => strtoupper(trim($request->symbol)),
            ]);

            $notify[] = ['success', 'Symbol updated successfully!'];
            return redirect()->route('admin.symbol-list.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Symbol Update Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error updating symbol: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function destroy($id)
    {
        try {
            $symbol = SymbolList::findOrFail($id);
            $symbol->delete(); // permanently deletes from DB

            $notify[] = ['success', 'Symbol deleted permanently!'];
            return redirect()->route('admin.symbol-list.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Symbol Delete Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error deleting symbol: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }
}