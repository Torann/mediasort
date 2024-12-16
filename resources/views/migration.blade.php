<?php echo "<?php\n" ?>

use Torann\MediaSort\Manager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('{{ $table }}', function(Blueprint $table) {
            $table->string('{{ $attachment }}_file_name')->nullable();
            $table->integer('{{ $attachment }}_file_size')->nullable();
            $table->string('{{ $attachment }}_content_type')->nullable();
            $table->timestamp('{{ $attachment }}_updated_at')->nullable();
            @if($queueable)$table->tinyInteger('{{ $attachment }}_queue_state')->default(Manager::QUEUE_DONE);@endif
            @if($queueable)$table->string('{{ $attachment }}_queued_file')->nullable()->default(null);@endif
            @if($queueable)$table->timestamp('{{ $attachment }}_queued_at')->nullable();@endif

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('{{ $table }}', function(Blueprint $table) {
            $table->dropColumn('{{ $attachment }}_file_name');
            $table->dropColumn('{{ $attachment }}_file_size');
            $table->dropColumn('{{ $attachment }}_content_type');
            $table->dropColumn('{{ $attachment }}_updated_at');
            @if($queueable)$table->dropColumn('{{ $attachment }}_queue_state');@endif
            @if($queueable)$table->dropColumn('{{ $attachment }}_queued_file');@endif
            @if($queueable)$table->dropColumn('{{ $attachment }}_queued_at');@endif

        });
    }
};