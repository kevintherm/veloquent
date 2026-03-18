<?php

use App\Domain\Records\Events\RecordChanged;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

it('broadcasts record changed events immediately', function () {
    $event = new RecordChanged(
        channel: 'private-superusers.01testsubscriberid00000000000',
        event: 'created',
        record: ['id' => '01testrecordid000000000000000'],
    );

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});
