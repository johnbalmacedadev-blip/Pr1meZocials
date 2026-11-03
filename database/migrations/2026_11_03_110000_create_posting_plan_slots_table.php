<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posting_plan_slots', function (Blueprint $table) {
            $table->id();
            $table->string('weekday', 10);
            $table->string('slot', 16);
            $table->string('format', 20);
            $table->string('title', 80);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['weekday', 'slot']);
        });

        // The weekly plan that was built into the page until now.
        $plan = [
            'monday' => [['poster', 'Motivation Monday'], ['reel', 'Product Education'], ['reel_poster', 'Company News'], ['reel', 'Business Opportunity']],
            'tuesday' => [['reel', 'Funny Taglish'], ['carousel', 'Benefits Carousel'], ['reel_poster', 'Behind the Scenes'], ['poster', 'Customer Story']],
            'wednesday' => [['image', 'Relatable Meme'], ['reel', 'Product Education'], ['ugc', 'Member Content'], ['reel', 'Member Journey']],
            'thursday' => [['reel', 'Office Chronicles'], ['poster', 'Product FAQ'], ['image', 'Customer Photo Review'], ['poster', 'How to Join']],
            'friday' => [['reel', 'Trending Reel'], ['poster', 'Product Promo'], ['reel_poster', 'Member Recognition'], ['reel', 'Business Invitation']],
            'saturday' => [['image', 'Lifestyle Photo'], ['reel', 'Member Testimonial'], ['carousel', 'Event Coverage'], ['poster', 'Bundle Offer']],
            'sunday' => [['poster', 'Faith & Hope'], ['reel', 'Community Reel'], ['reel_poster', 'Ask Pr1me Z'], ['reel', 'Member Success Story']],
        ];

        $rows = [];
        foreach ($plan as $weekday => $slots) {
            foreach (['morning', 'afternoon', 'flex', 'evening'] as $index => $slot) {
                $rows[] = [
                    'weekday' => $weekday,
                    'slot' => $slot,
                    'format' => $slots[$index][0],
                    'title' => $slots[$index][1],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('posting_plan_slots')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_plan_slots');
    }
};
