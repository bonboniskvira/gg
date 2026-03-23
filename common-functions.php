<?php
// Common functions used across multiple pages

if (!function_exists('formatBulletinTextPreview')) {
    function formatBulletinTextPreview($text) {
        $text = preg_replace('/\[b\](.*?)\[\/b\]/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/\[i\](.*?)\[\/i\]/s', '<em>$1</em>', $text);
        $text = preg_replace('/\[u\](.*?)\[\/u\]/s', '<u>$1</u>', $text);
        $text = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/s', '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>', $text);
        return $text;
    }
}

if (!function_exists('formatBulletinText')) {
    function formatBulletinText($text) {
        // Convert [b]text[/b] to <strong>text</strong>
        $text = preg_replace('/\[b\](.*?)\[\/b\]/s', '<strong>$1</strong>', $text);
        
        // Convert [i]text[/i] to <em>text</em>
        $text = preg_replace('/\[i\](.*?)\[\/i\]/s', '<em>$1</em>', $text);
        
        // Convert [u]text[/u] to <u>text</u>
        $text = preg_replace('/\[u\](.*?)\[\/u\]/s', '<u>$1</u>', $text);
        
        // Convert [url=link]text[/url] to <a href="link" target="_blank">text</a>
        $text = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/s', '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>', $text);
        
        return $text;
    }
}
?>
