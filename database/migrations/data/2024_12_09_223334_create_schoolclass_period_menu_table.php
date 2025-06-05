<?php

use App\Menu;
use App\Process;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Menu::query()->updateOrCreate(['old' => Process::SCHOOLCLASS_PERIOD], [
            'parent_id' => Menu::query()->where('old', Process::CONFIGURATIONS_TOOLS)->firstOrFail()->getKey(),
            'process' => Process::SCHOOLCLASS_PERIOD,
            'title' => 'Atualizar etapas da turma em lote',
            'order' => 0,
            'parent_old' => Process::CONFIGURATIONS_TOOLS,
            'link' => '/atualiza-etapa-turma',
        ]);
    }

    public function down(): void
    {
        Menu::query()->where('old', Process::SCHOOLCLASS_PERIOD)->delete();
    }
};
