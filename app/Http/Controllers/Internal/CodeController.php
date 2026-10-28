<?php

namespace App\Http\Controllers\Internal;

use App\Actions\Codes\SendCode;
use App\Codes\CodeNotSent;
use App\Http\Controllers\Controller;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rizq asking for a verification code to go out on WhatsApp. Answered at
 * once, so Rizq knows whether to send the code by email instead: 201 when
 * WhatsApp took it, otherwise an error with the reason. A code WhatsApp
 * takes but cannot deliver comes back to Rizq later, signed the same way.
 */
class CodeController extends Controller
{
    public function store(Request $request, SendCode $send): JsonResponse
    {
        $request->merge(['phone' => PhoneNumber::normalize($request->string('phone')->toString())]);

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'regex:/^\d{4,8}$/'],
            'language' => ['required', 'in:ar,en'],
            'purpose' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $delivery = $send->handle(
                $validated['reference'],
                $validated['phone'],
                $validated['code'],
                $validated['language'],
                $validated['purpose'] ?? null,
            );
        } catch (CodeNotSent $exception) {
            return response()->json(['error' => $exception->reason, 'message' => $exception->getMessage()], $exception->status);
        }

        return response()->json([
            'id' => $delivery->id,
            'status' => $delivery->status->value,
            'language' => $delivery->language,
        ], $delivery->wasRecentlyCreated ? 201 : 200);
    }
}
