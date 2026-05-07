<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     /**
     * Add LTP tracking + original placed price to oiiv_order_book.
     *
     * current_ltp          = live LTP fetched by FetchOiivOrderLtps command
     * ltp_updated_at       = when LTP was last fetched
     * original_placed_price = the price at the time of first placement (never changes)
     *                         placed_price = current/latest price (changes on modify)
     */
    public function up(): void
    {
        Schema::table('oiiv_order_book', function (Blueprint $table) {
            $table->decimal('current_ltp', 12, 2)
                  ->nullable()
                  ->after('last_modified_price')
                  ->comment('Live LTP fetched by background command');
 
            $table->timestamp('ltp_updated_at')
                  ->nullable()
                  ->after('current_ltp')
                  ->comment('When current_ltp was last refreshed');
 
            $table->decimal('original_placed_price', 12, 2)
                  ->nullable()
                  ->after('ltp_updated_at')
                  ->comment('Price at first placement — never overwritten on modify');
        });
    }
 
    public function down(): void
    {
        Schema::table('oiiv_order_book', function (Blueprint $table) {
            $table->dropColumn(['current_ltp', 'ltp_updated_at', 'original_placed_price']);
        });
    }
};
