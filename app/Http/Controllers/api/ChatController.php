<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatSendRequest;
use App\Http\Requests\SenderReceiverRequest;
use App\Models\Message;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function sendMessage(ChatSendRequest $request)
    {
        try {
            $data = [
                'sender_id' => $request->sender_id,
                'receiver_id' => $request->receiver_id,
                'message' => $request->message,
                'reply_to' => $request->reply_to ?? null,
                'status' => 'sent',
                'created_at' => now(),
            ];

            if ($request->hasFile('file_path')) {
                $file = $request->file('file_path');
                $filename = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads/chats/assets', $filename, 'public');
                $data['file_path'] = asset('storage/' . $filePath);
            }

            $message = Message::create($data);

            if ($message) {
                return response()->json(['code' => 201, 'message' => 'Message sent successfully!'], 201);
            }
            return response()->json(['code' => 500, 'message' => 'Something Went Wrong!'], 500);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    public function markAsRead(Request $request)
    {
        $validate  =  Validator::make($request->all(), [
            'message_id' => 'required',
        ]);
        if ($validate->fails()) {
            return response()->json(['code' => 401, 'message' => $validate->errors()], 401);
        }
        try {
            // check if the message exist with the status of sent 
            $checkMessageExist = Message::where('id', $request->message_id)
                ->where('status', 'sent')
                ->first();
            if ($checkMessageExist) {
                $checkMessageExist->status = 'readed';
                $checkMessageExist->updated_at = now();
                $checkMessageExist->save();
                return response()->json(['code' => 200, 'message' => 'Message Readed successfully!'], 200);
            }
            return response()->json(['code' => 400, 'message' => 'Message Not Found!'], 400);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'message' => $e->getMessage()], 500);
        }
    }


    public function deleteMessage(Request $request)
    {
        $validate  =  Validator::make($request->all(), [
            'message_id' => 'required',
            'sender_id' => 'required',
        ]);
        if ($validate->fails()) {
            return response()->json(['code' => 401, 'message' => $validate->errors()], 401);
        }
        $message = Message::where('id', $request->message_id)->where('sender_id', $request->sender_id)->first();
        if ($message) {
            $message->delete();
            return response()->json(['message' => 'Message deleted successfully', 'code' => 200], 200);
        }
        return response()->json(['code' => 404,  'error' => 'Message not found!'], 404);
    }

    public function getAllChatMessages(Request $request)
    {

        $validate = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'code' => 401,
                'message' => $validate->errors()
            ], 401);
        }

        try {
            $userId = $request->user_id;

            $messages = DB::table('messages as m')
                ->join('users as sender', 'sender.id', '=', 'm.sender_id')
                ->join('users as receiver', 'receiver.id', '=', 'm.receiver_id')
                ->whereNull('m.deleted_at')
                ->whereRaw('m.id = (
                SELECT MAX(id) 
                FROM messages 
                WHERE 
                    ((sender_id = ? OR receiver_id = ?) AND 
                    ((sender_id = m.sender_id AND receiver_id = m.receiver_id) 
                    OR (sender_id = m.receiver_id AND receiver_id = m.sender_id)))
            )', [$userId, $userId])
                ->select(
                    'm.*',
                    'sender.name as sender_name',
                    'sender.email as sender_email',
                    'receiver.name as receiver_name',
                    'receiver.email as receiver_email',
                )
                ->get();

            if ($messages->isNotEmpty()) {
                return response()->json([
                    'code' => 200,
                    'message' => 'Chat messages found successfully',
                    'data' => $messages
                ], 200);
            }
            return response()->json([
                'code' => 404,
                'message' => 'No chat found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getInnerChat(SenderReceiverRequest $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Fetch paginated messages with sender and receiver data
            $messages = DB::table('messages as m')
                ->join('users as sender', 'sender.id', '=', 'm.sender_id')
                ->join('users as receiver', 'receiver.id', '=', 'm.receiver_id')
                ->whereNull('m.deleted_at')
                ->where(function ($query) use ($request) {
                    $query->where('m.sender_id', $request->sender_id)
                        ->where('m.receiver_id', $request->receiver_id);
                })
                ->orWhere(function ($query) use ($request) {
                    $query->where('m.sender_id', $request->receiver_id)
                        ->where('m.receiver_id', $request->sender_id);
                })
                ->select(
                    'm.*',
                    'sender.name as sender_name',
                    'sender.email as sender_email',
                    'receiver.name as receiver_name',
                    'receiver.email as receiver_email',
                )
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'code' => 200,
                'message' => 'Chat messages retrieved successfully',
                'data' => $messages->items(),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                    'last_page' => $messages->lastPage(),
                    'results' => count($messages->items()),
                    'next_page_url' => $messages->nextPageUrl(),
                    'prev_page_url' => $messages->previousPageUrl(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    
}
