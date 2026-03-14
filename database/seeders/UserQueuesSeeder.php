<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Queue;

class UserQueuesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::find(9);
        
        if (!$owner) {
            $this->command->error("User with ID 9 not found.");
            return;
        }

        // Create 20 queues for this user
        $queues = Queue::factory()->count(20)->create([
            'user_id' => $owner->id,
            'business_id' => $owner->business_id, // ensure you have a business assigned or handle accordingly
        ]);

        foreach ($queues as $queue) {
            // Find a random customer, or create one if absolutely no customers exist
            $customer = User::where('role', 'customer')->inRandomOrder()->first();
            
            if (!$customer) {
                // Assuming User factory exists to create dummy users
                $customer = User::factory()->create(['role' => 'customer']);
            }

            // Assign the customer to this queue
            $queue->users()->attach($customer->id, [
                'status' => 'waiting',
                'ticket_number' => 'T-' . rand(1000, 9999), 
                'position' => 1
            ]);
        }
        
        $this->command->info("20 queues created successfully for user ID 9. Random customers assigned.");
    }
}
