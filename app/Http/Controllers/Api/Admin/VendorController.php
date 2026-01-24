<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateVendorRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin', description: 'Admin-only endpoints')]
class VendorController extends Controller
{
    #[OA\Post(
        path: '/api/admin/vendors',
        tags: ['Admin'],
        summary: 'Create a new vendor (Admin only)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'business_name', 'subscription_plan'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Vendor'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'vendor@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password'),
                    new OA\Property(property: 'business_name', type: 'string', example: 'Vendor Corp'),
                    new OA\Property(property: 'subscription_plan', type: 'string', enum: ['free', 'basic', 'premium'], example: 'basic'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Vendor created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Vendor created successfully'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'John Vendor'),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'vendor@example.com'),
                                new OA\Property(property: 'user_type', type: 'string', example: 'vendor'),
                                new OA\Property(
                                    property: 'vendor_profile',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'business_name', type: 'string', example: 'Vendor Corp'),
                                        new OA\Property(property: 'subscription_plan', type: 'string', example: 'basic'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Admin access required'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(CreateVendorRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::query()->create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'user_type' => UserType::Vendor,
            ]);

            VendorProfile::query()->create([
                'user_id' => $user->id,
                'business_name' => $request->business_name,
                'subscription_plan' => $request->subscription_plan,
            ]);

            return $user;
        });

        $user->load('vendorProfile');

        AuditLog::log('vendor_created', $user, [
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Vendor created successfully',
            'user' => $user,
        ], 201);
    }
}
