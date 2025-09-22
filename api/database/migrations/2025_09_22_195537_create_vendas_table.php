<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendas', function (Blueprint $table) {
            $table->id();
            $table->string('cliente', 150);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('lucro', 12, 2)->default(0);
            $table->string('status', 20)->default('completed'); 
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index('cliente');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas');
    }
};
