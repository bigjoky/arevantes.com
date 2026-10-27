<?php

use App\Services\Finance\ReceivedInvoiceAudit;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

// Local-only orchestrator. Production procedure requires a separately authorized context.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || DB::getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'arevantes_deploy01_incremental_test' || ! in_array(config('database.connections.'.DB::getDefaultConnection().'.host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Only the isolated DEPLOY01 synthetic database is permitted.');
}
$cluster = new Process(['container', 'exec', 'apple-dev-postgres', 'psql', '-U', 'developer', '-d', 'postgres', '-Atc', 'SELECT system_identifier::text FROM pg_control_system()']);
$cluster->mustRun();
if (trim($cluster->getOutput()) !== DB::selectOne('SELECT system_identifier::text AS id FROM pg_control_system()')->id) {
    throw new RuntimeException('Local cluster identity mismatch.');
}
if (DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker !== 'arevantes:deploy01:synthetic:v1') {
    throw new RuntimeException('Synthetic database marker required.');
}
$report = DB::transaction(function () {
    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
    DB::statement("SET LOCAL statement_timeout='60s'");
    Artisan::call('time:audit-instants', ['--json' => true]);
    $temporal = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $economic = app(ReceivedInvoiceAudit::class)->report();
    $pending = array_values(array_diff(array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php'))), DB::table('migrations')->pluck('migration')->all()));
    $triggers = DB::select("SELECT c.relname AS table_name,t.tgname,t.tgenabled FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND NOT t.tgisinternal ORDER BY c.relname,t.tgname");
    $indexes = DB::select("SELECT c.relname,i.indisvalid,i.indisready,i.indisunique FROM pg_index i JOIN pg_class c ON c.oid=i.indexrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' ORDER BY c.relname");
    $columns = DB::select("SELECT table_name,column_name,data_type,datetime_precision FROM information_schema.columns WHERE table_schema='public' AND column_name LIKE '%\\_utc' ESCAPE '\\' ORDER BY table_name,column_name");
    $constraints = DB::select("SELECT c.conname,t.relname AS table_name,c.contype,c.convalidated FROM pg_constraint c JOIN pg_class t ON t.oid=c.conrelid JOIN pg_namespace n ON n.oid=c.connamespace WHERE n.nspname='public' ORDER BY t.relname,c.conname");
    $duplicateNumbers = DB::table('sales_documents')->select('number')->selectRaw('count(*) AS copies')->whereNotNull('number')->where('number', '<>', '')->groupBy('number')->havingRaw('count(*)>1')->get();
    $invalidSeries = DB::table('document_series')->where('next_number', '<', 1)->pluck('id')->all();
    $findings = [];
    $add = function ($severity, $code, $evidence) use (&$findings) {
        if ($evidence) {
            $findings[] = compact('severity', 'code', 'evidence');
        }
    };
    $add('bloqueante', 'pending_migrations', $pending);
    $expected = [];
    foreach (['document_deliveries', 'customer_portal_accesses', 'work_order_attachments', 'portfolio_projects', 'planning_scenarios', 'opportunities', 'work_orders', 'time_entries', 'crm_activities', 'work_order_reschedules'] as $table) {
        $expected[] = $table.':precise_instants_guard';
    }
    $expected = [...$expected, 'purchase_documents:purchase_received_identity', 'expenses:expense_received_identity', 'supplier_payments:supplier_payment_received_identity', 'journal_entries:journal_received_identity', 'bank_reconciliations:reconciliation_received_identity', 'received_invoice_identities:received_identity_preserve'];
    $active = array_map(fn ($t) => $t->table_name.':'.$t->tgname, array_filter($triggers, fn ($t) => in_array($t->tgenabled, ['O', 'A'], true)));
    $add('bloqueante', 'missing_required_triggers', array_values(array_diff($expected, $active)));
    $expectedColumns = [];
    foreach (['DocumentDelivery', 'CustomerPortalAccess', 'WorkOrderAttachment', 'PortfolioProject', 'PlanningScenario', 'Opportunity', 'WorkOrder', 'TimeEntry', 'CrmActivity', 'WorkOrderReschedule'] as $modelName) {
        $class = 'App\\Models\\'.$modelName;
        $model = new $class;
        foreach ($model->preciseInstantFields() as $field) {
            $expectedColumns[] = $model->getTable().':'.$field.'_utc';
        }
    }
    $actualColumns = array_map(fn ($c) => $c->table_name.':'.$c->column_name, $columns);
    $add('bloqueante', 'missing_utc_companions', array_values(array_diff($expectedColumns, $actualColumns)));
    $requiredIndexes = ['received_invoice_fiscal_identity_unique', 'purchase_supplier_document_identity_unique', 'expense_issuer_document_identity_unique'];
    foreach (['work_orders' => ['scheduled_start', 'scheduled_end'], 'time_entries' => ['started_at'], 'opportunities' => ['created_at', 'first_response_due_at'], 'crm_activities' => ['starts_at', 'due_at'], 'portfolio_projects' => ['published_at'], 'work_order_attachments' => ['published_at']] as $table => $fields) {
        foreach ($fields as $field) {
            $requiredIndexes[] = $table.'_'.$field.'_instant_idx';
        }
    }
    $add('bloqueante', 'missing_required_indexes', array_values(array_diff($requiredIndexes, array_column($indexes, 'relname'))));
    $add('bloqueante', 'invalid_utc_column_type', array_values(array_filter($columns, fn ($c) => $c->data_type !== 'timestamp with time zone' || $c->datetime_precision !== 6)));
    $add('requiere_revision_manual', 'unvalidated_constraints', array_values(array_filter($constraints, fn ($c) => ! $c->convalidated)));
    $add('bloqueante', 'invalid_indexes', array_values(array_filter($indexes, fn ($i) => ! $i->indisvalid || ! $i->indisready)));
    $add('bloqueante', 'disabled_triggers', array_values(array_filter($triggers, fn ($t) => ! in_array($t->tgenabled, ['O', 'A'], true))));
    foreach ($temporal['fields'] as $field => $counts) {
        foreach (['divergent' => 'bloqueante', 'invalid_or_nonexistent' => 'requiere_revision_manual', 'ambiguous' => 'requiere_revision_manual', 'legacy_unambiguous' => 'advertencia'] as $kind => $severity) {
            $add($severity, 'time_'.$kind, $counts[$kind] ? ['field' => $field, 'count' => $counts[$kind]] : []);
        }
    }
    $add('bloqueante', 'economic_migration_collisions', $economic['migration_constraints_blocked'] ? $economic['migration_blockers'] : []);
    $add('requiere_revision_manual', 'economic_findings', $economic['deployment_review_required'] ? ['details' => 'economic report; counts may overlap'] : []);
    $add('bloqueante', 'duplicate_document_numbers', $duplicateNumbers->all());
    $add('bloqueante', 'invalid_series_counter', $invalidSeries);
    $add('informativo', 'writer_compatibility', ['status' => 'requires reviewed source inventory; database cannot prove all external clients upgraded']);

    return ['read_only' => true, 'historical_repairs' => false, 'timezone' => ['app' => config('app.timezone'), 'business' => $temporal['timezone'], 'postgres' => DB::selectOne('SHOW timezone')->TimeZone, 'php_tzdb' => timezone_version_get()], 'pending_migrations' => $pending, 'temporal' => $temporal, 'economic' => $economic, 'utc_columns' => $columns, 'constraints' => $constraints, 'triggers' => $triggers, 'indexes' => $indexes, 'findings' => $findings, 'numbering_scope' => 'duplicate stored numbers and positive counters; legacy prefix/maximum reconciliation requires manual review'];
});
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),PHP_EOL;
exit(count(array_filter($report['findings'], fn ($f) => in_array($f['severity'], ['bloqueante', 'requiere_revision_manual'], true))) ? 1 : 0);
