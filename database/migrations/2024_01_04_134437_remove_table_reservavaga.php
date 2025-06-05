<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pmieducar.candidato_reserva_vaga');
        Schema::dropIfExists('pmieducar.reserva_vaga');
    }
};
