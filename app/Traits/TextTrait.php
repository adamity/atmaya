<?php

namespace App\Traits;

trait TextTrait
{
    private function commandText($text = null)
    {
        $message = "<b>Commands :</b> \n\n";
        $message .= "/customer_service - ask us anything about our product\n";
        $message .= "/preconsult - start preconsult\n";
        if ($text) $message = $text . "\n\n" . $message;
        return $message;
    }
}
