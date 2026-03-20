<?php

namespace Tests\Unit;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Requests\BaseRecordRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordRequestFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_auto_fill_fields_removes_null_timestamps()
    {
        $collection = Collection::create([
            'name' => 'testposts',
            'type' => CollectionType::Base,
            'fields' => [
                ['name' => 'title', 'type' => 'text', 'nullable' => false],
            ],
        ]);

        $request = new class extends BaseRecordRequest {
            public function testFilterAutoFillFields(array $data, Collection $collection): array
            {
                return $this->filterAutoFillFields($data, $collection);
            }
        };

        $data = [
            'title' => 'Test Post',
            'created_at' => null,
            'updated_at' => null,
            'id' => 'some-id',
            'token' => null,
            'other_field' => 'value',
        ];

        $filtered = $request->testFilterAutoFillFields($data, $collection);

        // Should keep non-auto-fill fields and non-null auto-fill fields
        $this->assertArrayHasKey('title', $filtered);
        $this->assertArrayHasKey('other_field', $filtered);
        $this->assertEquals('Test Post', $filtered['title']);
        $this->assertEquals('value', $filtered['other_field']);

        // Should remove null auto-fill fields
        $this->assertArrayNotHasKey('created_at', $filtered);
        $this->assertArrayNotHasKey('updated_at', $filtered);
        $this->assertArrayNotHasKey('token', $filtered);

        // Should keep non-null auto-fill fields
        $this->assertArrayHasKey('id', $filtered);
        $this->assertEquals('some-id', $filtered['id']);
    }

    public function test_filter_password_field_removes_null_password_for_auth_collection()
    {
        $collection = Collection::create([
            'name' => 'testusers',
            'type' => CollectionType::Auth,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'nullable' => false],
                ['name' => 'password', 'type' => 'text', 'nullable' => true],
            ],
        ]);

        $request = new class extends BaseRecordRequest {
            public function testFilterPasswordField(array $data, Collection $collection): array
            {
                return $this->filterPasswordField($data, $collection);
            }
        };

        $data = [
            'email' => 'test@example.com',
            'password' => null,
            'other_field' => 'value',
        ];

        $filtered = $request->testFilterPasswordField($data, $collection);

        // Should keep non-password fields
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayHasKey('other_field', $filtered);
        $this->assertEquals('test@example.com', $filtered['email']);
        $this->assertEquals('value', $filtered['other_field']);

        // Should remove null password field for auth collection
        $this->assertArrayNotHasKey('password', $filtered);
    }

    public function test_filter_password_field_keeps_null_password_for_regular_collection()
    {
        $collection = Collection::create([
            'name' => 'testposts',
            'type' => CollectionType::Base,
            'fields' => [
                ['name' => 'title', 'type' => 'text', 'nullable' => false],
                ['name' => 'password', 'type' => 'text', 'nullable' => true],
            ],
        ]);

        $request = new class extends BaseRecordRequest {
            public function testFilterPasswordField(array $data, Collection $collection): array
            {
                return $this->filterPasswordField($data, $collection);
            }
        };

        $data = [
            'title' => 'Test Post',
            'password' => null,
        ];

        $filtered = $request->testFilterPasswordField($data, $collection);

        // Should keep all fields including null password for regular collection
        $this->assertArrayHasKey('title', $filtered);
        $this->assertArrayHasKey('password', $filtered);
        $this->assertEquals('Test Post', $filtered['title']);
        $this->assertNull($filtered['password']);
    }

    public function test_filter_password_field_keeps_non_null_password_for_auth_collection()
    {
        $collection = Collection::create([
            'name' => 'testusersauth',
            'type' => CollectionType::Auth,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'nullable' => false],
                ['name' => 'password', 'type' => 'text', 'nullable' => true],
            ],
        ]);

        $request = new class extends BaseRecordRequest {
            public function testFilterPasswordField(array $data, Collection $collection): array
            {
                return $this->filterPasswordField($data, $collection);
            }
        };

        $data = [
            'email' => 'test@example.com',
            'password' => 'newpassword123',
        ];

        $filtered = $request->testFilterPasswordField($data, $collection);

        // Should keep all fields including non-null password
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayHasKey('password', $filtered);
        $this->assertEquals('test@example.com', $filtered['email']);
        $this->assertEquals('newpassword123', $filtered['password']);
    }
}
