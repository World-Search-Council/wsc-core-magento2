<?php

namespace Wsc\Analytics\Model;

/**
 * AI & Search Bot Taxonomy engine for Magento 2.
 */
class BotTracker
{
    private const BOT_TAXONOMY = [
        // 1. AI Live RAG & User-driven Prompt Search Bots
        'ChatGPT-User'          => ['name' => 'ChatGPT-User', 'category' => 'ai', 'type' => 'rag'],
        'OAI-SearchBot'         => ['name' => 'OAI-SearchBot', 'category' => 'ai', 'type' => 'rag'],
        'Claude-User'           => ['name' => 'Claude-User', 'category' => 'ai', 'type' => 'rag'],
        'Claude-SearchBot'      => ['name' => 'Claude-SearchBot', 'category' => 'ai', 'type' => 'rag'],
        'Claude-Web'            => ['name' => 'Claude-Web', 'category' => 'ai', 'type' => 'rag'],
        'PerplexityBot'         => ['name' => 'PerplexityBot', 'category' => 'ai', 'type' => 'rag'],
        'Perplexity-User'       => ['name' => 'Perplexity-User', 'category' => 'ai', 'type' => 'rag'],
        'Gemini-Deep-Research'  => ['name' => 'Gemini-Deep-Research', 'category' => 'ai', 'type' => 'rag'],
        'YouBot'                => ['name' => 'YouBot', 'category' => 'ai', 'type' => 'rag'],

        // 2. AI Model Pre-Training & Offline Crawlers
        'GPTBot'                => ['name' => 'GPTBot', 'category' => 'ai', 'type' => 'training'],
        'ClaudeBot'             => ['name' => 'ClaudeBot', 'category' => 'ai', 'type' => 'training'],
        'CCBot'                 => ['name' => 'CCBot', 'category' => 'ai', 'type' => 'training'],
        'Bytespider'            => ['name' => 'Bytespider', 'category' => 'ai', 'type' => 'training'],
        'Google-Extended'       => ['name' => 'Google-Extended', 'category' => 'ai', 'type' => 'training'],
        'Meta-ExternalAgent'    => ['name' => 'Meta-ExternalAgent', 'category' => 'ai', 'type' => 'training'],
        'Meta-ExternalFetcher'  => ['name' => 'Meta-ExternalFetcher', 'category' => 'ai', 'type' => 'training'],
        'FacebookBot'           => ['name' => 'FacebookBot', 'category' => 'ai', 'type' => 'training'],
        'xAI-Bot'               => ['name' => 'xAI-Bot', 'category' => 'ai', 'type' => 'training'],
        'GrokBot'               => ['name' => 'GrokBot', 'category' => 'ai', 'type' => 'training'],
        'DeepSeekBot'           => ['name' => 'DeepSeekBot', 'category' => 'ai', 'type' => 'training'],
        'Amazonbot'             => ['name' => 'Amazonbot', 'category' => 'ai', 'type' => 'training'],
        'Cohere-AI'             => ['name' => 'Cohere-AI', 'category' => 'ai', 'type' => 'training'],
        'Diffbot'               => ['name' => 'Diffbot', 'category' => 'ai', 'type' => 'training'],
        'Applebot-Extended'     => ['name' => 'Applebot-Extended', 'category' => 'ai', 'type' => 'training'],
        'Scrapy'                => ['name' => 'Scrapy', 'category' => 'ai', 'type' => 'training'],

        // 3. Search Engine Crawlers
        'Googlebot-Mobile'      => ['name' => 'Googlebot (Mobile)', 'category' => 'search', 'type' => 'search'],
        'Googlebot-Image'       => ['name' => 'Googlebot (Image)', 'category' => 'search', 'type' => 'search'],
        'Google-InspectionTool' => ['name' => 'Google Inspection Tool', 'category' => 'search', 'type' => 'search'],
        'GoogleOther'           => ['name' => 'GoogleOther', 'category' => 'search', 'type' => 'search'],
        'Googlebot'             => ['name' => 'Googlebot', 'category' => 'search', 'type' => 'search'],
        'Bingbot'               => ['name' => 'Bingbot', 'category' => 'search', 'type' => 'search'],
        'BingPreview'           => ['name' => 'BingPreview', 'category' => 'search', 'type' => 'search'],
        'DuckDuckBot'           => ['name' => 'DuckDuckBot', 'category' => 'search', 'type' => 'search'],
        'DuckDuckGo-Favicons-Bot' => ['name' => 'DuckDuckGo Favicons', 'category' => 'search', 'type' => 'search'],
        'YandexBot'             => ['name' => 'YandexBot', 'category' => 'search', 'type' => 'search'],
        'Baiduspider'           => ['name' => 'Baiduspider', 'category' => 'search', 'type' => 'search'],
        'Sogou'                 => ['name' => 'Sogou Spider', 'category' => 'search', 'type' => 'search'],
        'Exabot'                => ['name' => 'Exabot', 'category' => 'search', 'type' => 'search'],
        'Applebot'              => ['name' => 'Applebot', 'category' => 'search', 'type' => 'search'],
        'Qwantify'              => ['name' => 'Qwantify', 'category' => 'search', 'type' => 'search'],
        'archive.org_bot'       => ['name' => 'Internet Archive', 'category' => 'search', 'type' => 'search'],

        // 4. SEO & Auditing Tools
        'AhrefsBot'             => ['name' => 'AhrefsBot', 'category' => 'seo_tool', 'type' => 'seo_tool'],
        'AhrefsSiteAudit'       => ['name' => 'AhrefsSiteAudit', 'category' => 'seo_tool', 'type' => 'seo_tool'],
        'SemrushBot'            => ['name' => 'SemrushBot', 'category' => 'seo_tool', 'type' => 'seo_tool'],
        'DotBot'                => ['name' => 'DotBot (Moz)', 'category' => 'seo_tool', 'type' => 'seo_tool'],
        'MJ12bot'               => ['name' => 'MJ12bot (Majestic)', 'category' => 'seo_tool', 'type' => 'seo_tool'],
        'Screaming Frog SEO Spider' => ['name' => 'Screaming Frog', 'category' => 'seo_tool', 'type' => 'seo_tool'],
        'Sitebulb'              => ['name' => 'Sitebulb', 'category' => 'seo_tool', 'type' => 'seo_tool'],
    ];

    public function detectBot(?string $userAgent): ?array
    {
        if (empty($userAgent)) {
            return null;
        }

        foreach (self::BOT_TAXONOMY as $token => $info) {
            if (stripos($userAgent, $token) !== false) {
                return $info;
            }
        }

        if (preg_match('/(bot|crawler|spider|slurp|archiver|transcoder|fetcher)/i', $userAgent)) {
            return [
                'name' => 'Generic Crawler (' . substr($userAgent, 0, 30) . ')',
                'category' => 'other',
                'type' => 'other',
            ];
        }

        return null;
    }
}
