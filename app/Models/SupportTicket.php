<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Constants\Status;

class SupportTicket extends Model
{
    public function fullname(): Attribute
    {
        return new Attribute(
            get:fn () => $this->name,
        );
    }

    public function username(): Attribute
    {
        return new Attribute(
            get:fn () => $this->email,
        );
    }

    public function statusBadge(): Attribute
    {
        return new Attribute(
            get:fn () => $this->badgeData(),
        );
    }

    public function badgeData(){
        $html = '';
        $class = '';

        if(!request()->routeIs('admin*')){
            $class = 'py-2 px-3';
        }

        if($this->status == Status::TICKET_OPEN){
            $html = '<span class="badge badge--success '.$class.'">'.trans("Open").'</span>';
        }
        elseif($this->status == Status::TICKET_ANSWER){
            $html = '<span class="badge badge--primary '.$class.'">'.trans("Answered").'</span>';
        }

        elseif($this->status == Status::TICKET_REPLY){
            $html = '<span class="badge badge--warning '.$class.'">'.trans("Customer Reply").'</span>';
        }
        elseif($this->status == Status::TICKET_CLOSE){

            if($class){
                $class .= ' badge--danger';
            }else{
                $class = 'badge--dark';
            }

            $html = '<span class="badge  '.$class.'">'.trans("Closed").'</span>';
        }
        return $html;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supportMessage(){
        return $this->hasMany(SupportMessage::class);
    }

}
