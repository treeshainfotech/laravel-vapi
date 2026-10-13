<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Webhooks;

use Vapi\Core\Json\JsonSerializableType;
use Vapi\Types\ServerMessageAssistantRequest;
use Vapi\Types\ServerMessageAssistantSpeech;
use Vapi\Types\ServerMessageCallDeleted;
use Vapi\Types\ServerMessageCallDeleteFailed;
use Vapi\Types\ServerMessageCallEndpointingRequest;
use Vapi\Types\ServerMessageChatCreated;
use Vapi\Types\ServerMessageChatDeleted;
use Vapi\Types\ServerMessageConversationUpdate;
use Vapi\Types\ServerMessageEndOfCallReport;
use Vapi\Types\ServerMessageHandoffDestinationRequest;
use Vapi\Types\ServerMessageHang;
use Vapi\Types\ServerMessageKnowledgeBaseRequest;
use Vapi\Types\ServerMessageLanguageChangeDetected;
use Vapi\Types\ServerMessageModelOutput;
use Vapi\Types\ServerMessagePhoneCallControl;
use Vapi\Types\ServerMessageSessionCreated;
use Vapi\Types\ServerMessageSessionDeleted;
use Vapi\Types\ServerMessageSessionUpdated;
use Vapi\Types\ServerMessageSpeechUpdate;
use Vapi\Types\ServerMessageStatusUpdate;
use Vapi\Types\ServerMessageToolCalls;
use Vapi\Types\ServerMessageTranscript;
use Vapi\Types\ServerMessageTransferDestinationRequest;
use Vapi\Types\ServerMessageTransferUpdate;
use Vapi\Types\ServerMessageUserInterrupted;
use Vapi\Types\ServerMessageVoiceInput;
use Vapi\Types\ServerMessageVoiceRequest;

/**
 * Maps the `message.type` discriminator that Vapi sends to the SDK class
 * that deserializes it. The SDK's own ServerMessage union cannot be trusted
 * for this: it resolves every payload to the first union arm whose required
 * fields are satisfied, which is ServerMessageAssistantRequest for any
 * payload carrying a `type` string.
 */
final class ServerMessageTypeMap
{
    public const string END_OF_CALL_REPORT = 'end-of-call-report';

    public const string STATUS_UPDATE = 'status-update';

    public const string TOOL_CALLS = 'tool-calls';

    public const string TRANSCRIPT = 'transcript';

    /**
     * Vapi sends final transcripts under their own discriminator, not under
     * TRANSCRIPT. Both deserialize to ServerMessageTranscript and both raise
     * TranscriptReceived, so match on the event — or on BOTH constants when
     * you compare the raw `message.type` string.
     */
    public const string TRANSCRIPT_FINAL = 'transcript[transcriptType="final"]';

    private const array MAP = [
        'assistant-request' => ServerMessageAssistantRequest::class,
        'assistant.speechStarted' => ServerMessageAssistantSpeech::class,
        'call.delete.failed' => ServerMessageCallDeleteFailed::class,
        'call.deleted' => ServerMessageCallDeleted::class,
        'call.endpointing.request' => ServerMessageCallEndpointingRequest::class,
        'chat.created' => ServerMessageChatCreated::class,
        'chat.deleted' => ServerMessageChatDeleted::class,
        'conversation-update' => ServerMessageConversationUpdate::class,
        self::END_OF_CALL_REPORT => ServerMessageEndOfCallReport::class,
        'handoff-destination-request' => ServerMessageHandoffDestinationRequest::class,
        'hang' => ServerMessageHang::class,
        'knowledge-base-request' => ServerMessageKnowledgeBaseRequest::class,
        'language-change-detected' => ServerMessageLanguageChangeDetected::class,
        'model-output' => ServerMessageModelOutput::class,
        'phone-call-control' => ServerMessagePhoneCallControl::class,
        'session.created' => ServerMessageSessionCreated::class,
        'session.deleted' => ServerMessageSessionDeleted::class,
        'session.updated' => ServerMessageSessionUpdated::class,
        'speech-update' => ServerMessageSpeechUpdate::class,
        self::STATUS_UPDATE => ServerMessageStatusUpdate::class,
        self::TOOL_CALLS => ServerMessageToolCalls::class,
        self::TRANSCRIPT => ServerMessageTranscript::class,
        self::TRANSCRIPT_FINAL => ServerMessageTranscript::class,
        'transfer-destination-request' => ServerMessageTransferDestinationRequest::class,
        'transfer-update' => ServerMessageTransferUpdate::class,
        'user-interrupted' => ServerMessageUserInterrupted::class,
        'voice-input' => ServerMessageVoiceInput::class,
        'voice-request' => ServerMessageVoiceRequest::class,
    ];

    /**
     * @return ?class-string<JsonSerializableType>
     */
    public static function resolve(string $type): ?string
    {
        return self::MAP[$type] ?? null;
    }
}
