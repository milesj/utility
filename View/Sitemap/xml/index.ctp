<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
	<?php // Render without XmlEngine as we need the namespace in urlset
	if ($sitemap) {
		foreach ($sitemap as $item) {
			echo '<url>';

			foreach ($item as $key => $value) {
				echo sprintf('<%s>%s</%s>', $key, $value, $key);
			}

			echo '</url>';
		}
	} ?>
</urlset>