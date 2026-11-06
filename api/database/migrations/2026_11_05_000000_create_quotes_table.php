<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuotesTable extends Migration
{
    public function up()
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('budget_id');
            $table->string('client');
            $table->text('client_search');
            $table->text('event');
            $table->text('event_lines');
            $table->char('currency', 3);
            $table->decimal('subtotal', 30, 2);
            $table->decimal('total', 30, 2);
            $table->text('exchange_rates');
            $table->longText('input_json');
            $table->longText('result_json');
            $table->string('engine_version', 32);
            $table->timestamp('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotes');
    }
}
