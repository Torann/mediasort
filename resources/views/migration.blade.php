<?php echo "<?php\n" ?>

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Add{{ ucfirst($attachment) }}FieldsTo{{ studly_case($table) }}Table extends Migration
{
    /**
     * Make changes to the table.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('{{ $table }}', function(Blueprint $table) {
            $table->string('{{ $attachment }}_file_name')->nullable(){{ ($after ? "->after('{$after}')" : '') }};
            $table->integer('{{ $attachment }}_file_size')->nullable()->after('{{ $attachment }}_file_name');
            $table->string('{{ $attachment }}_content_type')->nullable()->after('{{ $attachment }}_file_size');
            $table->timestamp('{{ $attachment }}_updated_at')->nullable()->after('{{ $attachment }}_content_type');
        });
    }

    /**
     * Revert the changes to the table.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('{{ $table }}', function(Blueprint $table) {
            $table->dropColumn('{{ $attachment }}_file_name');
            $table->dropColumn('{{ $attachment }}_file_size');
            $table->dropColumn('{{ $attachment }}_content_type');
            $table->dropColumn('{{ $attachment }}_updated_at');
        });
    }
}