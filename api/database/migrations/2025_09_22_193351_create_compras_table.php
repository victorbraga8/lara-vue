<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('fornecedor', 150);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->index('fornecedor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
