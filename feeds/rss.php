<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/rss+xml; charset=utf-8');

$articles = latest_articles(20);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "
";
?>
<rss version="2.0">
    <channel>
        <title><?= htmlspecialchars(SITE_NAME, ENT_XML1, 'UTF-8'); ?></title>
        <link><?= htmlspecialchars(url(''), ENT_XML1, 'UTF-8'); ?></link>
        <description><?= htmlspecialchars(DEFAULT_META_DESCRIPTION, ENT_XML1, 'UTF-8'); ?></description>
        <language>id-ID</language>
        <?php foreach ($articles as $article): ?>
            <item>
                <title><?= htmlspecialchars((string)($article['title'] ?? ''), ENT_XML1, 'UTF-8'); ?></title>
                <link><?= htmlspecialchars(article_url((string)($article['slug'] ?? '')), ENT_XML1, 'UTF-8'); ?></link>
                <description><?= htmlspecialchars((string)($article['excerpt'] ?? ''), ENT_XML1, 'UTF-8'); ?></description>
                <pubDate><?= date(DATE_RSS, strtotime((string)($article['published_at'] ?? 'now'))); ?></pubDate>
                <guid><?= htmlspecialchars(article_url((string)($article['slug'] ?? '')), ENT_XML1, 'UTF-8'); ?></guid>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>
