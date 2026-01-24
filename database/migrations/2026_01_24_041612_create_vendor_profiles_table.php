<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Create vendor_profiles table
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('subscription_plan')->default('free');
            $table->timestamps();
        });

        // Step 2: Migrate existing vendor data
        $vendors = DB::table('users')
            ->where('user_type', 'vendor')
            ->get(['id', 'business_name', 'subscription_plan', 'created_at', 'updated_at']);

        foreach ($vendors as $vendor) {
            DB::table('vendor_profiles')->insert([
                'user_id' => $vendor->id,
                'business_name' => $vendor->business_name ?? 'Unknown Business',
                'subscription_plan' => $vendor->subscription_plan ?? 'free',
                'created_at' => $vendor->created_at,
                'updated_at' => $vendor->updated_at,
            ]);
        }

        // Step 3: Update 'customer' user_type to 'buyer'
        DB::table('users')
            ->where('user_type', 'customer')
            ->update(['user_type' => 'buyer']);

        // Step 4: Remove vendor-specific columns from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['business_name', 'subscription_plan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add back the columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('name');
            $table->string('subscription_plan')->nullable()->after('password');
        });

        // Step 2: Restore vendor data from vendor_profiles
        $vendorProfiles = DB::table('vendor_profiles')
            ->join('users', 'vendor_profiles.user_id', '=', 'users.id')
            ->get(['vendor_profiles.user_id', 'vendor_profiles.business_name', 'vendor_profiles.subscription_plan']);

        foreach ($vendorProfiles as $profile) {
            DB::table('users')
                ->where('id', $profile->user_id)
                ->update([
                    'business_name' => $profile->business_name,
                    'subscription_plan' => $profile->subscription_plan,
                ]);
        }

        // Step 3: Revert 'buyer' back to 'customer'
        DB::table('users')
            ->where('user_type', 'buyer')
            ->update(['user_type' => 'customer']);

        // Step 4: Drop vendor_profiles table
        Schema::dropIfExists('vendor_profiles');
    }
};
