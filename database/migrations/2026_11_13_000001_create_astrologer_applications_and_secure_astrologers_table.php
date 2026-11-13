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
        // 1. Create astrologer_applications table
        if (!Schema::hasTable('astrologer_applications')) {
            Schema::create('astrologer_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->index('status');
                $table->index('admin_id');

                // Shared / Media
                $table->string('image')->nullable();
                $table->string('experience');
                $table->integer('price')->default(0);
                $table->integer('call_price')->default(0);
                $table->integer('original_price')->default(0);
                $table->integer('chat_minutes')->default(0);
                $table->integer('call_minutes')->default(0);

                // English Profile Content
                $table->string('en_name');
                $table->string('en_expertise');
                $table->string('en_languages');
                $table->text('en_excerpt')->nullable();
                $table->longText('en_description')->nullable();

                // Hindi Profile Content
                $table->string('hi_name');
                $table->string('hi_expertise');
                $table->string('hi_languages');
                $table->text('hi_excerpt')->nullable();
                $table->longText('hi_description')->nullable();

                // Reviewer / Audit Fields
                $table->foreignId('reviewed_by')->nullable()->constrained('admins')->onDelete('set null');
                $table->timestamp('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('submitted_at')->nullable();

                $table->timestamps();
            });
        }

        // 2. Safely reconcile existing duplicate rows in astrologers before adding unique constraint
        if (Schema::hasTable('astrologers')) {
            // Remap any known seed records where name matches another admin
            $yashpal = DB::table('admins')->where('email', 'yashpal@astrosetu.com')->first();
            if ($yashpal) {
                DB::table('astrologers')
                    ->whereIn('name', ['Yashpal Sharma', 'यशपाल शर्मा'])
                    ->where('admin_id', '!=', $yashpal->id)
                    ->update(['admin_id' => $yashpal->id]);
            }

            $suman = DB::table('admins')->where('email', 'suman@astrosetu.com')->first();
            if ($suman) {
                DB::table('astrologers')
                    ->whereIn('name', ['Suman Sharma', 'सुमन शर्मा'])
                    ->where('admin_id', '!=', $suman->id)
                    ->update(['admin_id' => $suman->id]);
            }

            // Remove any remaining identical (admin_id, language) duplicate rows keeping the latest
            $duplicates = DB::table('astrologers')
                ->select('admin_id', 'language', DB::raw('COUNT(*) as count'))
                ->groupBy('admin_id', 'language')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicates as $dup) {
                $rows = DB::table('astrologers')
                    ->where('admin_id', $dup->admin_id)
                    ->where('language', $dup->language)
                    ->orderBy('updated_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();

                if ($rows->count() > 1) {
                    $deleteIds = $rows->slice(1)->pluck('id');
                    DB::table('astrologers')->whereIn('id', $deleteIds)->delete();
                }
            }

            // Add unique constraint if not present
            $existingIndexes = collect(Schema::getIndexes('astrologers'))->pluck('name')->all();
            if (!in_array('astrologers_admin_id_language_unique', $existingIndexes)) {
                Schema::table('astrologers', function (Blueprint $table) {
                    $table->unique(['admin_id', 'language'], 'astrologers_admin_id_language_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('astrologers')) {
            $existingIndexes = collect(Schema::getIndexes('astrologers'))->pluck('name')->all();

            if (in_array('astrologers_admin_id_language_unique', $existingIndexes)) {
                // In MySQL (InnoDB), dropping a compound unique index that covers a foreign key column (admin_id)
                // throws Error 1553 ("Cannot drop index: needed in a foreign key constraint") if no other index
                // exists to satisfy the foreign key constraint.
                // We first ensure a standalone index on admin_id exists before dropping the unique constraint.
                if (!in_array('astrologers_admin_id_index', $existingIndexes) && !in_array('astrologers_admin_id_foreign', $existingIndexes)) {
                    try {
                        Schema::table('astrologers', function (Blueprint $table) {
                            $table->index('admin_id', 'astrologers_admin_id_index');
                        });
                    } catch (\Throwable $e) {
                        // Suppress if already exists
                    }
                }

                try {
                    Schema::table('astrologers', function (Blueprint $table) {
                        $table->dropUnique('astrologers_admin_id_language_unique');
                    });
                } catch (\Throwable $e) {
                    // Fallback for strict database configurations:
                    try {
                        Schema::table('astrologers', function (Blueprint $table) {
                            $table->dropForeign(['admin_id']);
                            $table->dropUnique('astrologers_admin_id_language_unique');
                            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
                        });
                    } catch (\Throwable $ex) {
                        // Suppress if already dropped
                    }
                }
            }
        }

        Schema::dropIfExists('astrologer_applications');
    }
};
