<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalesMade extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'id' => $this->sales->id,
            'branch_name' => $this->sales->branch->name,
            'total' => $this->sales->total,
            'title' => 'New Purchase'
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): array
    {
        return [
            'id' => $this->sales->id,
            'branch_name' => $this->sales->branch->name,
            'total' => $this->sales->total,
            'title' => 'New Purchase'
        ];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('New Purchase at ' . $this->sales->branch->name)
            ->body($this->sales->branch->name)
            ->action('View Session', '/operational/sales')
            ->data([
                'id' => $this->sales->id,
                'branch_name' => $this->sales->branch->name,
                'total' => $this->sales->total,
                'title' => 'New Purchase'
            ])
            // ->badge()
            // ->dir()
            // ->image()
            // ->lang()
            // ->renotify()
            // ->requireInteraction()
            // ->tag()
            // ->vibrate()
            ->options(['TTL' => 1000]);
    }
}
