<?php

namespace Tests\Unit;

use App\Services\QrisDynamicService;
use App\Support\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrisDynamicServiceTest extends TestCase
{
    use RefreshDatabase;

    private function sampleStaticQris(QrisDynamicService $service): string
    {
        // TLV minimal tanpa CRC; CRC dihitung seperti converter.
        $merchantAccount =
            '0011ID.DANA.WWW'.
            '011793600912345678901'.
            '0215ID1026554625172';

        $withoutCrc =
            '000201'.
            '010211'.
            '26'.str_pad((string) strlen($merchantAccount), 2, '0', STR_PAD_LEFT).$merchantAccount.
            '52045812'.
            '5303360'.
            '5802ID'.
            '5911KEDAI TJOAN'.
            '6007Jakarta'.
            '62070703A01';

        $crcInput = $withoutCrc.'6304';
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('crc16');
        $method->setAccessible(true);
        $crc = $method->invoke($service, $crcInput);

        return $crcInput.$crc;
    }

    public function test_convert_static_to_dynamic_with_amount(): void
    {
        $service = app(QrisDynamicService::class);
        $static = $this->sampleStaticQris($service);

        $this->assertTrue($service->validate($static)['valid']);

        $dynamic = $service->convert($static, 47500);
        $summary = $service->parseSummary($dynamic);

        $this->assertSame('dynamic', $summary['method']);
        $this->assertSame('KEDAI TJOAN', $summary['merchant_name']);
        $this->assertStringContainsString('540547500', $dynamic);
        $this->assertTrue($service->validate($dynamic)['valid']);
    }

    public function test_for_amount_uses_saved_payload(): void
    {
        $service = app(QrisDynamicService::class);
        $static = $this->sampleStaticQris($service);

        ShopSettings::put(['qris_payload' => $static]);

        $result = $service->forAmount(15000);

        $this->assertTrue($result['enabled']);
        $this->assertSame('dynamic', $result['mode']);
        $this->assertSame(15000, $result['amount']);
        $this->assertNotEmpty($result['qr_data_uri']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $result['qr_data_uri']);
    }

    public function test_for_amount_falls_back_without_payload(): void
    {
        ShopSettings::put(['qris_payload' => '']);

        $result = app(QrisDynamicService::class)->forAmount(25000);

        $this->assertFalse($result['enabled']);
        $this->assertSame('static', $result['mode']);
        $this->assertNull($result['payload']);
    }
}
