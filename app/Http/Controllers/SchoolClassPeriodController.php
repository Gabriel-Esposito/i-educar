<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolClassPeriodRequest;
use App\Models\LegacySchoolClass;
use App\Models\LegacySchoolClassStage;
use App\Process;
use Carbon\Carbon;
use iEducar\Support\Exceptions\Exception;
use Illuminate\Support\Facades\DB;

class SchoolClassPeriodController extends Controller
{
    private function getDate($date, $format)
    {
        try {
            return Carbon::createFromFormat($format, $date);
        } catch (Exception) {
            return null;
        }
    }

    public function update(SchoolClassPeriodRequest $request)
    {
        $stageDates = $request->get('etapas');

        $schoolClasses = LegacySchoolClass::query()
            ->whereInstitution($request->get('ref_cod_instituicao'))
            ->whereYearEq($request->get('ano'))
            ->when($request->get('escola'), fn ($q, $schools) =>  $q->whereIn('ref_ref_cod_escola', $schools))
            ->when($request->get('curso'), fn ($q, $courses) => $q->whereIn('ref_cod_curso', $courses))
            ->whereHas('schoolClassStages', function ($q) use($request){
                $q->when($request->get('ref_cod_modulo'), function ($q, $stageType) {
                    return $q->where('ref_cod_modulo', $stageType);
                });
            })
            ->whereHas('course', fn ($q) => $q->whereStandardCalendar(0))
            ->pluck('cod_turma');

        if ($schoolClasses->count() === 0) {
            return redirect()->route('schoolclass-period.edit')->withInput()->with('error', 'Nenhuma turma encontrada com os filtros selecionados');
        }

        DB::beginTransaction();
        foreach ($stageDates as $stage => $data) {
            try {
                $startDate = $data['data_inicio'] ?? null;
                $endDate = $data['data_fim'] ?? null;

                $updateData = array_filter([
                    'data_inicio' => $startDate ? $this->getDate($startDate, 'd/m/Y') : null,
                    'data_fim' => $endDate ? $this->getDate($endDate, 'd/m/Y') : null,
                    'dias_letivos' => $data['dias_letivos'] ?? null,
                ]);

                if (!empty($updateData)) {
                    LegacySchoolClassStage::whereIn('ref_cod_turma', $schoolClasses)
                        ->where('sequencial', $stage)
                        ->update($updateData);
                }
            } catch (Exception) {
                DB::rollBack();
                session()->flash('error', 'Atualização em lote não realizada.');
            }
        }

        DB::commit();
        session()->flash('success', "Atualização em lote efetuada com sucesso em {$schoolClasses->count()} turmas.");

        return redirect()->route('schoolclass-period.edit');
    }

    public function edit()
    {
        $this->menu(Process::SCHOOLCLASS_PERIOD);
        $this->breadcrumb('Atualizar etapas da turma', [
            url('/intranet/educar_configuracoes_index.php') => 'Configurações',
        ]);

        return view('schoolclass-period.edit', ['user' => request()->user()]);
    }
}
