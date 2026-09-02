<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Vendor\LaravelAuthentication\DTO\AuthenticationContext;
use Vendor\LaravelAuthentication\Exceptions\AccountLockedException;
use Vendor\LaravelAuthentication\Exceptions\AuthenticationException;
use Vendor\LaravelAuthentication\Exceptions\InvalidCredentialsException;
use Vendor\LaravelAuthentication\Services\Passkey\PasskeyService;

class PasskeyController extends Controller
{
    public function __construct(
        protected readonly PasskeyService $passkeyService
    ) {}

    /**
     * Generate options for Passkey login ceremony.
     */
    public function loginOptions(Request $request): JsonResponse
    {
        $identifier = $request->query('identifier') ?: $request->input('identifier');
        $options = $this->passkeyService->generateRequestOptions($identifier ? (string) $identifier : null);

        return response()->json($options->toArray());
    }

    /**
     * Authenticate user with Passkey assertion response.
     */
    public function login(Request $request): JsonResponse
    {
        $payload = $request->all();
        $context = AuthenticationContext::fromRequest($request);

        try {
            $result = $this->passkeyService->authenticate($payload, $context);

            return response()->json([
                'status'   => 'success',
                'message'  => __('authentication::messages.sign_in_btn'),
                'redirect' => config('authentication.redirects.login', '/dashboard'),
                'token'    => $result->token,
                'user'     => $result->user,
            ]);
        } catch (AccountLockedException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 423);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => __('authentication::messages.passkey_failed'),
            ], 422);
        } catch (AuthenticationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Generate options for Passkey registration ceremony (authenticated user).
     */
    public function registerOptions(Request $request): JsonResponse
    {
        /** @var Authenticatable|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $options = $this->passkeyService->generateCreationOptions($user);

        return response()->json($options->toArray());
    }

    /**
     * Save newly registered Passkey credential (authenticated user).
     */
    public function register(Request $request): JsonResponse
    {
        /** @var Authenticatable|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $name = (string) ($request->input('name') ?: 'Passkey (' . now()->format('M d, H:i') . ')');

        try {
            $credential = $this->passkeyService->registerPasskey($user, $request->all(), $name);

            return response()->json([
                'status'     => 'success',
                'message'    => __('authentication::messages.passkey_registered'),
                'credential' => [
                    'id'   => $credential->id,
                    'name' => $credential->name,
                ],
            ]);
        } catch (AuthenticationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a registered Passkey.
     */
    public function destroy(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Authenticatable|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $deleted = $this->passkeyService->deletePasskey($user, $id);

        if ($request->wantsJson()) {
            return response()->json([
                'status'  => $deleted ? 'success' : 'not_found',
                'message' => $deleted 
                    ? __('authentication::messages.passkey_deleted') 
                    : 'Passkey not found.',
            ], $deleted ? 200 : 404);
        }

        return back()->with(
            $deleted ? 'status' : 'error', 
            $deleted ? __('authentication::messages.passkey_deleted') : 'Passkey not found.'
        );
    }
}
