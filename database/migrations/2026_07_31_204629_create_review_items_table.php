<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_items', function (Blueprint $table) {
            $table->id();

            /**
             * Polymorphic because the queue is about *classification*, not
             * about food entries specifically — SUI-17 may route other
             * unresolved records through the same surface.
             */
            $table->morphs('reviewable');

            /**
             * The text as the user typed it. Copied rather than read back
             * through the relation, so the queue still shows what failed even
             * after the entry that produced it is edited away.
             */
            $table->text('text');

            /**
             * The best trigram similarity anything in the catalog reached, so
             * an operator can tell a near miss worth an alias from a genuine
             * absence worth a new catalog entry. Null where nothing scored.
             */
            $table->decimal('score', 4, 3)->nullable();

            $table->string('status')->default(ReviewStatus::Pending->value)->index();
            $table->timestamps();

            /**
             * One open question per record: re-saving an entry updates its
             * review item rather than queueing the same miss twice.
             */
            $table->unique(['reviewable_type', 'reviewable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
    }
};
