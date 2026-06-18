<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RealTimeNotificationController extends Controller
{
    /**
     * Stream notifications via Server-Sent Events (SSE)
     */
    public function streamNotifications(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        return response()->stream(function () use ($user) {
            $lastCheck = now();
            
            while (true) {
                try {
                    // Check for new notifications
                    $notifications = Notification::where('user_id', $user->id)
                        ->whereNull('read_at')
                        ->where('created_at', '>', $lastCheck)
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get();

                    if ($notifications->count() > 0) {
                        $data = [
                            'notifications' => $notifications->toArray(),
                            'count' => $notifications->count()
                        ];
                        
                        echo "data: " . json_encode($data) . "\n\n";
                        ob_flush();
                        flush();
                        
                        $lastCheck = now();
                    }

                    // Check every 2 seconds
                    sleep(2);

                } catch (\Exception $e) {
                    Log::error('Error in notification stream', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive'
        ]);
    }
}
