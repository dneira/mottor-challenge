<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use App\Domain\Quotes\{Currency, Money};

class AddExactListingTotalToQuotes extends Migration
{
    public function up()
    {
        // String cents avoid SQLite numeric-affinity float coercion in portable listing tests.
        // The same exact projection is used in MariaDB; no result_json is loaded on list reads.
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('total_minor', 32)->nullable();
        });
        DB::table('quotes')->select('id', 'result_json')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $snapshot = json_decode($row->result_json, true, 64, JSON_THROW_ON_ERROR);
                $cents = Money::of($snapshot['total'], Currency::from($snapshot['currency']))->minor();
                DB::table('quotes')->where('id', $row->id)->update(['total_minor' => (string) $cents]);
            }
        });
    }

    public function down()
    {
        // Native DROP COLUMN (SQLite >= 3.35 / MariaDB), avoiding Laravel 8's DBAL requirement.
        DB::statement('ALTER TABLE quotes DROP COLUMN total_minor');
    }
}
