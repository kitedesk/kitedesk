<?php

use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('agents download an attachment with its contents', function () {
    $message = TicketMessage::factory()->create();
    $media = $message->addMedia(UploadedFile::fake()->createWithContent('notes.txt', 'The printer is on fire'))->toMediaCollection('attachments');

    $response = $this->actingAs(User::factory()->agent()->create())->get(route('attachments.show', $media));

    $response->assertDownload('notes.txt');
    expect($response->streamedContent())->toBe('The printer is on fire');
});

test('an attachment whose file is missing from storage is not found and is logged', function () {
    $message = TicketMessage::factory()->create();
    $media = $message->addMedia(UploadedFile::fake()->createWithContent('notes.txt', 'The printer is on fire'))->toMediaCollection('attachments');
    Storage::disk('local')->delete($media->getPathRelativeToRoot());
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['media'] === $media->id);

    $response = $this->actingAs(User::factory()->agent()->create())->get(route('attachments.show', $media));

    $response->assertNotFound();
});
