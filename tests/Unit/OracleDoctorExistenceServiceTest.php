<?php

namespace Tests\Unit;

use App\Services\Oracle\OracleConnectionService;
use App\Services\Oracle\OracleDoctorExistenceService;
use App\Support\NationalIdentifier;
use Illuminate\Support\Facades\Log;
use Mockery;
use PDO;
use PDOStatement;
use Tests\TestCase;

class OracleDoctorExistenceServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_doctor_exists_normalizes_numeric_identifiers_before_calling_oracle(): void
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('bindValue')->once()->with(':p_register_no', '368393');
        $statement->shouldReceive('bindValue')->once()->with(':p_id_no', '29903021801215');
        $statement->shouldReceive('bindParam')
            ->once()
            ->withArgs(function (string $parameter, mixed &$value, int $type, int $length): bool {
                $this->assertSame(':p_doctor_yn', $parameter);
                $this->assertSame(PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, $type);
                $this->assertSame(10, $length);
                $value = 'Y';

                return true;
            });
        $statement->shouldReceive('execute')->once();

        $connection = Mockery::mock(PDO::class);
        $connection->shouldReceive('prepare')->once()->andReturn($statement);

        $oracleConnectionService = Mockery::mock(OracleConnectionService::class);
        $oracleConnectionService->shouldReceive('make')->once()->andReturn($connection);

        $service = new OracleDoctorExistenceService($oracleConnectionService);

        $this->assertTrue($service->doctorExists(' ٣٦٨٣٩٣ ', ' 29903 021801215 '));
    }

    public function test_doctor_exists_logs_warning_when_oracle_returns_not_found(): void
    {
        Log::spy();

        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('bindValue')->once()->with(':p_register_no', '368393');
        $statement->shouldReceive('bindValue')->once()->with(':p_id_no', '29903021801215');
        $statement->shouldReceive('bindParam')
            ->once()
            ->withArgs(function (string $parameter, mixed &$value): bool {
                $this->assertSame(':p_doctor_yn', $parameter);
                $value = 'N';

                return true;
            });
        $statement->shouldReceive('execute')->once();

        $connection = Mockery::mock(PDO::class);
        $connection->shouldReceive('prepare')->once()->andReturn($statement);

        $oracleConnectionService = Mockery::mock(OracleConnectionService::class);
        $oracleConnectionService->shouldReceive('make')->once()->andReturn($connection);

        $service = new OracleDoctorExistenceService($oracleConnectionService);

        $this->assertFalse($service->doctorExists('368393', '29903021801215'));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Oracle doctor lookup returned not found.', [
                'driver' => 'pdo_oci',
                'register_no_input' => '368393',
                'register_no_normalized' => '368393',
                'register_no_changed' => false,
                'id_no_input' => '**********1215',
                'id_no_normalized' => '**********1215',
                'id_no_fingerprint' => NationalIdentifier::fingerprint('29903021801215'),
                'id_no_changed' => false,
                'doctor_flag' => 'N',
            ]);
    }
}
