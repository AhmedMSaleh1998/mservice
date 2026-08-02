<?php

namespace Tests\Unit;

use App\Services\Oracle\OracleConnectionService;
use App\Services\Oracle\OracleDoctorDataLookupService;
use Mockery;
use PDO;
use PDOStatement;
use Tests\TestCase;

class OracleDoctorDataLookupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.oracle.register_lookup_enabled', true);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_find_by_register_no_normalizes_input_and_decodes_oracle_json_payload(): void
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('bindValue')->once()->with(':p_register_no', '213934');
        $statement->shouldReceive('bindParam')
            ->once()
            ->withArgs(function (string $parameter, mixed &$value, int $type, int $length): bool {
                $this->assertSame(':p_result', $parameter);
                $this->assertSame(PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, $type);
                $this->assertSame(32767, $length);
                $value = '{"ID":213714,"REGISTER_NO":213934,"DOCTORNAME":"احمد ممدوح احمد قنديل","SPECIALIZATION_ARABIC_NAME":"طب حالات حرجة","CONSULT_ID":4,"CONSULT_NAME":"إستشاري"}';

                return true;
            });
        $statement->shouldReceive('execute')->once();

        $connection = Mockery::mock(PDO::class);
        $connection->shouldReceive('prepare')->once()->andReturn($statement);

        $oracleConnectionService = Mockery::mock(OracleConnectionService::class);
        $oracleConnectionService->shouldReceive('make')->once()->andReturn($connection);

        $service = new OracleDoctorDataLookupService($oracleConnectionService);

        $this->assertSame([
            'id' => 213714,
            'register_no' => '213934',
            'doctor_name' => 'احمد ممدوح احمد قنديل',
            'specialization_arabic_name' => 'طب حالات حرجة',
            'consult_id' => 4,
            'consult_name' => 'إستشاري',
        ], $service->findByRegisterNo(' ٢١٣٩٣٤ '));
    }

    public function test_find_by_register_no_returns_null_when_oracle_returns_empty_payload(): void
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('bindValue')->once()->with(':p_register_no', '213934');
        $statement->shouldReceive('bindParam')
            ->once()
            ->withArgs(function (string $parameter, mixed &$value): bool {
                $this->assertSame(':p_result', $parameter);
                $value = '';

                return true;
            });
        $statement->shouldReceive('execute')->once();

        $connection = Mockery::mock(PDO::class);
        $connection->shouldReceive('prepare')->once()->andReturn($statement);

        $oracleConnectionService = Mockery::mock(OracleConnectionService::class);
        $oracleConnectionService->shouldReceive('make')->once()->andReturn($connection);

        $service = new OracleDoctorDataLookupService($oracleConnectionService);

        $this->assertNull($service->findByRegisterNo('213934'));
    }
}
