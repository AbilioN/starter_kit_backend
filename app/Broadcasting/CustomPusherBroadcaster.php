<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Pusher\Pusher;

class CustomPusherBroadcaster extends PusherBroadcaster
{
    /**
     * Create a new Pusher instance.
     *
     * @param  array  $config
     * @return \Pusher\Pusher
     */
    protected function pusher(array $config)
    {
        // Se o host estiver vazio, usar o cluster padrão do Pusher
        $options = $config['options'] ?? [];
        
        if (empty($options['host']) && !empty($options['cluster'])) {
            $options['host'] = "api-{$options['cluster']}.pusher.com";
        }
        
        $pusher = new Pusher(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            $options
        );

        return $pusher;
    }
}
