<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'VENDORA API',
    version: '1.0.0',
    description: 'API documentation for VENDORA.'
)]
#[OA\Server(url: '/', description: 'Application base URL')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer'
)]
#[OA\Tag(name: 'Auth', description: 'Authentication endpoints')]
#[OA\Tag(name: 'User', description: 'User endpoints')]
#[OA\Tag(name: 'Product', description: 'Product endpoints')]
#[OA\Tag(name: 'Category', description: 'Category endpoints')]
#[OA\Tag(name: 'Inventory', description: 'Inventory endpoints')]
#[OA\Tag(name: 'Customer', description: 'Customer endpoints')]
#[OA\Tag(name: 'Order', description: 'Order endpoints')]
#[OA\Tag(name: 'Payment', description: 'Payment endpoints')]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/register',
        tags: ['Auth'],
        summary: 'Register a new buyer account',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registration successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Registration successful'),
                        new OA\Property(property: 'token', type: 'string', example: '1|e2b7x...'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                                new OA\Property(property: 'user_type', type: 'string', example: 'buyer'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'user_type' => UserType::Buyer,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        AuditLog::log('register', $user);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Auth'],
        summary: 'Login and receive an access token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'vendor@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                        new OA\Property(property: 'token', type: 'string', example: '1|e2b7x...'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Vendor Corp'),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'vendor@example.com'),
                                new OA\Property(property: 'user_type', type: 'string', example: 'vendor'),
                                new OA\Property(
                                    property: 'vendor_profile',
                                    type: 'object',
                                    nullable: true,
                                    description: 'Vendor profile (only for vendors)',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'business_name', type: 'string', example: 'Vendor Corp'),
                                        new OA\Property(property: 'subscription_plan', type: 'string', example: 'basic'),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'stores',
                                    type: 'array',
                                    description: 'Stores owned by this user',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'name', type: 'string', example: 'Main Store'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'assigned_stores',
                                    type: 'array',
                                    description: 'Stores where user is assigned as staff',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 2),
                                            new OA\Property(property: 'name', type: 'string', example: 'Branch Store'),
                                            new OA\Property(property: 'role', type: 'string', example: 'cashier'),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-01-10T10:00:00Z'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'error', type: 'string', example: 'INVALID_CREDENTIALS'),
                        new OA\Property(property: 'message', type: 'string', example: 'The email or password you entered is incorrect.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The email field is required.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: ['email' => ['The email field is required.']]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            AuditLog::log('login_failed', null, ['email' => $request->email]);

            return response()->json([
                'success' => false,
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'The email or password you entered is incorrect.',
            ], 401);
        }

        $user = User::query()->where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        AuditLog::log('login', $user);

        // Build user data with store information
        $userData = $user->toArray();

        // Include vendor profile for vendors
        if ($user->isVendor()) {
            $userData['vendor_profile'] = $user->vendorProfile;
        }

        // Include owned stores
        $userData['stores'] = $user->ownedStores()
            ->select(['id', 'name', 'code', 'is_active'])
            ->get();

        // Include assigned stores (for staff members)
        $userData['assigned_stores'] = $user->assignedStores()
            ->select(['stores.id', 'stores.name', 'stores.code', 'store_user.role'])
            ->get()
            ->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'code' => $store->code,
                    'role' => $store->pivot->role,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => $userData,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        tags: ['Auth'],
        summary: 'Logout the authenticated user',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        AuditLog::log('logout', $user);

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
