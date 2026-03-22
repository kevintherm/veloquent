<?php

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Domain\SchemaManagement\Enums\SchemaOperation;
use App\Domain\SchemaManagement\Models\SchemaJob;
use App\Models\Superuser;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $superuser = Superuser::factory()->create();
    $record = Record::fromTable('superusers');
    $record->forceFill($superuser->toArray());
    $record->exists = true;

    $mock = mock(TokenAuthService::class);
    $mock->shouldReceive('authenticate')->andReturn($record);
    $mock->shouldReceive('extractTokenFromRequest')->andReturn('test-token');

    $this->withHeaders(['Authorization' => 'Bearer test-token']);
});

test('it lists corrupt collections with their respective schema jobs', function () {
    $collection = Collection::factory()->create(['name' => 'corrupt_collection']);

    SchemaJob::create([
        'collection_id' => $collection->id,
        'operation' => SchemaOperation::Create,
        'table_name' => 'corrupt_table',
        'started_at' => now(),
    ]);

    $response = getJson('/api/schema/corrupt');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.collection.name', 'corrupt_collection');
    $response->assertJsonPath('data.0.operation', 'create');
});
