<?php

namespace App\Notify;

use App\Lib\CurlRequest;
use App\Notify\NotifyProcess;
use App\Notify\Notifiable;

class Push extends NotifyProcess implements Notifiable{

    /**
    * Device Id of receiver
    *
    * @var array
    */
	public $deviceId;

    public $redirectUrl;


    /**
    * Assign value to properties
    *
    * @return void
    */
	public function __construct(){
		$this->statusField = 'firebase_status';
		$this->body = 'firebase_body';
		$this->globalTemplate = 'firebase_template';
		$this->notifyConfig = 'firebase_config';
	}


    /**
    * Send notification
    *
    * @return void|bool
    */
	public function send(){
		//get message from parent
		$message = $this->getMessage();
        if ($message) {
            try{
                $data = [
                    'registration_ids'=>$this->deviceId,
                    'notification'=>[
                        'title'=> $this->subject,
                        'body'=> $message,
                        'icon'=> getImage(getFilePath('logoIcon') .'/logo.png'),
                        'click_action'=>$this->redirectUrl,
                        'priority'=> 'high'
                    ],
                    'data'=>[
                        'for_app'=>'NEW_SIGNAL'
                    ]
                ];

                $dataString = json_encode($data);

                $headers = [
                    'Authorization:key=' . $this->setting->firebase_config->serverKey,
                    'Content-Type: application/json',
                    'priority:high'
                ];

                CurlRequest::curlPostContent('https://fcm.googleapis.com/fcm/send',$dataString,$headers);
                $this->createLog('firebase');
            }catch(\Exception $e){
                $this->createErrorLog($e->getMessage());
                session()->flash('firebase_error',$e->getMessage());
            }
        }

    }



    /**
    * Configure some properties
    *
    * @return void
    */
	public function prevConfiguration(){
		if ($this->user) {
            $this->deviceId = $this->user->deviceTokens()->pluck('token')->toArray();
			$this->receiverName = $this->user->fullname;
		}
		$this->toAddress = $this->deviceId;
	}
}
