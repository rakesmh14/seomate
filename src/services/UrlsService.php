<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use craft\models\Site;

use vaersaagod\seomate\SEOMate;
use vaersaagod\seomate\helpers\SEOMateHelper;

use yii\base\Exception;

/**
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class UrlsService extends Component
{
    /**
     * Gets the canonical URL from context
     * 
     * @param $context
     * @return null|string
     */
    public function getCanonicalUrl($context)
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();

        $overrideObject = $context['seomate'] ?? null;

        if ($overrideObject && isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        if ($overrideObject && isset($overrideObject['canonicalUrl']) && $overrideObject['canonicalUrl'] !== '') {
            return $overrideObject['canonicalUrl'];
        }

        /** @var Element $element */
        if ($overrideObject && isset($overrideObject['element'])) {
            $element = $overrideObject['element'];
        } else {
            $element = $craft->urlManager->getMatchedElement();
        }
        
        if ($element && $element->getUrl()) {
            return $element->getUrl();
        }
        
        // Get the request URL, and clean it.
        $url = strip_tags(html_entity_decode($craft->getRequest()->getFullPath(), ENT_NOQUOTES, 'UTF-8'));
        
        return UrlHelper::url($url);
    }

    /**
     * Gets the alternate URLs from context
     * 
     * @param $context
     * @return array
     */
    public function getAlternateUrls($context): array
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();
        $alternateUrls = [];

        try {
            $currentSite = $craft->getSites()->getCurrentSite();
        } catch (SiteNotFoundException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return [];
        }

        $overrideObject = $context['seomate'] ?? null;

        if ($overrideObject && isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        if (!$settings->outputAlternate) {
            return [];
        }

        /** @var Element $element */
        if ($overrideObject && isset($overrideObject['element'])) {
            $element = $overrideObject['element'];
        } else {
            $element = $craft->urlManager->getMatchedElement();
        }

        if (!$element) {
            return [];
        }

        $fallbackSite = null;

        if (is_string($settings->alternateFallbackSiteHandle) && !empty($settings->alternateFallbackSiteHandle)) {
            $fallbackSite = $craft->getSites()->getSiteByHandle($settings->alternateFallbackSiteHandle);

            if ($fallbackSite && $fallbackSite->id !== null) {
                $url = $craft->getElements()->getElementUriForSite($element->getId(), $fallbackSite->id);

                if ($url) {
                    $url = $this->prepAlternateUrlForSite($url, $fallbackSite);
                } else {
                    $url = $this->prepAlternateUrlForSite('', $fallbackSite);
                }

                if ($url && $url !== '') {
                    $alternateUrls[] = [
                        'url' => $url,
                        'language' => 'x-default'
                    ];
                }
            }
        }

        foreach ($craft->getSites()->getAllSites() as $site) {
            if ($site->id !== $currentSite->id) {
                if ($fallbackSite === null || $fallbackSite->id !== $site->id) {
                    $url = $craft->getElements()->getElementUriForSite($element->getId(), $site->id);

                    if ($url !== false && $url !== null) { // if element was not available in the given site, this happens
                        $url = $this->prepAlternateUrlForSite($url, $site);

                        if ($url && $url !== '') {
                            $alternateUrls[] = [
                                'url' => $url,
                                'language' => strtolower(str_replace('_', '-', $site->language))
                            ];
                        }
                    }
                }
            }
        }

        return $alternateUrls;
    }

    /**
     * Returns a fully qualified site URL from uri and site
     * 
     * @param string $uri
     * @param Site $site
     * @return string
     */
    private function prepAlternateUrlForSite($uri, $site): string
    {
        $url = ($uri === '__home__') ? '' : $uri;
        
        if (!UrlHelper::isAbsoluteUrl($url)) {
            try {
                $url = UrlHelper::siteUrl($url, null, null, $site->id);
            } catch (Exception $e) {
                $url = '';
                Craft::error($e->getMessage(), __METHOD__);
            }
        }

        return $url;
    }
}
