<?php

use App\Support\Database\MigrationUtils;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    use MigrationUtils;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->dropView('public.educacenso_record20');

        $this->executeSqlFile(
            database_path('sqls/views/public.educacenso_record20-2022-05-19.sql')
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->dropView('public.educacenso_record20');

        $this->executeSqlFile(
            database_path('sqls/views/public.educacenso_record20-2020-04-13.sql')
        );
    }
};
