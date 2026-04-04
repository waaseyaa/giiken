<?php

declare(strict_types=1);

return [
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),

    'log_level' => getenv('LOG_LEVEL') ?: 'warning',

    'environment' => getenv('APP_ENV') ?: 'production',

    'database' => null,

    'config_dir' => getenv('WAASEYAA_CONFIG_DIR') ?: __DIR__ . '/sync',

    'files_dir' => getenv('WAASEYAA_FILES_DIR') ?: __DIR__ . '/../storage/files',

    'jwt_secret' => getenv('WAASEYAA_JWT_SECRET') ?: '',
    'api_keys' => [],
    'auth' => [
        'dev_fallback_account' => filter_var(
            getenv('WAASEYAA_DEV_FALLBACK_ACCOUNT') ?: false,
            FILTER_VALIDATE_BOOLEAN,
        ),
    ],

    'upload_max_bytes' => 50 * 1024 * 1024, // 50 MiB — knowledge artifacts can be large
    'upload_allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
        'video/mp4',
        'video/ogg',
        'text/plain',
        'application/octet-stream',
    ],

    'cors_origins' => ['http://localhost:3000', 'http://127.0.0.1:3000'],

    // Sovereignty profile: 'local' | 'self_hosted' | 'northops'
    // Controls defaults for storage, embeddings, llm_provider, transcriber,
    // vector_store, and queue_backend.
    'sovereignty_profile' => getenv('GIIKEN_SOVEREIGNTY_PROFILE') ?: 'local',

    'i18n' => [
        'languages' => [
            ['id' => 'en', 'label' => 'English', 'is_default' => true],
            ['id' => 'fr', 'label' => 'French', 'is_default' => false],
            ['id' => 'oj', 'label' => 'Ojibwe', 'is_default' => false],
        ],
    ],

    'ssr' => [
        'theme' => getenv('WAASEYAA_SSR_THEME') ?: '',
        'cache_max_age' => (int) (getenv('WAASEYAA_SSR_CACHE_MAX_AGE') ?: 300),
    ],

    'ai' => [
        'embedding_provider' => getenv('WAASEYAA_EMBEDDING_PROVIDER') ?: '',
        'ollama_endpoint' => getenv('WAASEYAA_OLLAMA_ENDPOINT') ?: 'http://127.0.0.1:11434/api/embeddings',
        'ollama_model' => getenv('WAASEYAA_OLLAMA_MODEL') ?: 'nomic-embed-text',
        'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
        'openai_embedding_model' => getenv('WAASEYAA_OPENAI_EMBEDDING_MODEL') ?: 'text-embedding-3-small',
        'embedding_fields' => [
            'knowledge_item' => ['title', 'body', 'summary'],
        ],
    ],
];
