<?php

class SearchResultsFilters
{
    protected $advancedArgsArray = array();
    protected $basicArgsArray = array();
    protected $filterCount;
    protected $filterMessage;
    protected $searchResults;

    function __construct($searchResults)
    {
        /* @var $searchResults SearchResultsView */
        $this->searchResults = $searchResults;
        $this->filterCount = 0;
        $this->filterMessage = '';
    }

    protected function createFilterWithRemoveX($filter, $resetUrl)
    {
        $link = AvantSearch::getSearchFilterResetLink($resetUrl);
        $this->filterMessage .= "<span class='search-filter'>$filter$link</span>";
        $this->filterCount++;
    }

    protected function emitAdvancedSearchFilters()
    {
        if (empty($this->advancedArgsArray))
            return;

        // Get all the arguments from the query string.
        $queryArgs = explode('&', http_build_query($_GET));

        foreach ($this->advancedArgsArray as $advancedIndex => $advancedArg)
        {
            // Make a copy of the arguments array that the following code can modify without affecting the original.
            $args = $queryArgs;

            // Examine each arg/value pair, looking for the one that matches the current Advanced Search arg.
            foreach ($args as $argsIndex => $pair)
            {
                // Skip any args that are not for Advanced Search.
                if (strpos($pair, 'advanced') === false)
                    continue;

                // Create a prefix for this arg based on its index e.g. 'advanced[0'. Note that the arg is encoded and
                // so the prefix will actually look like 'advanced%5B0' The prefix length includes index length.
                $advancedPrefix = urlencode('advanced[');
                $prefixLength = strlen($advancedPrefix) + ($advancedIndex <= 9 ? 1 : 2);
                $prefix = substr($pair, 0, $prefixLength);

                if (strpos($prefix, "$advancedPrefix$advancedIndex") === 0)
                {
                    // Remove this arg from the copy of the query args array.
                    unset($args[$argsIndex]);
                }
            }

            // Reconstruct the query string from the args array minus the arg that just got removed.
            $query = '?';
            foreach ($args as $pair)
            {
                if (strlen($query) > 1)
                {
                    $query .= '&';
                }
                $query .= $pair;
            }

            $this->createFilterWithRemoveX($advancedArg, $query);
        }
    }

    protected function emitBasicSearchFilters()
    {
        if (empty($this->basicArgsArray))
            return;

        // Get all the arguments from the query string.
        $queryArgs = explode('&', http_build_query($_GET));

        foreach ($this->basicArgsArray as $argName => $basicArg)
        {
            // Make a copy of the arguments array that the following code can modify without affecting the original.
            $args = $queryArgs;

            // Examine each arg/value pair, looking for the one that matches the current basic arg.
            foreach ($args as $argsIndex => $pair)
            {
                // Skip any args that are for Advanced Search.
                if (strpos($pair, 'advanced') === 0)
                    continue;

                $encodedBasicArg = $argName . '=' . urlencode($basicArg['value']);
                if ($encodedBasicArg == $pair)
                {
                    // Remove this arg from the copy of the query args array.
                    unset($args[$argsIndex]);
                }
            }

            // Reconstruct the query string from the args array minus the arg that just got removed.
            $query = '?';
            foreach ($args as $pair)
            {
                if (strlen($query) > 1)
                {
                    $query .= '&';
                }
                $query .= $pair;
            }

            $this->createFilterWithRemoveX($basicArg['display'], $query);
        }
    }

    protected function emitElasticsearchFilters()
    {
        $query = $this->searchResults->getQuery();

        $avantElasticsearchFacets = new AvantElasticsearchFacets();
        $filterBarFacets = $avantElasticsearchFacets->getFilterBarFacets($query);

        foreach ($filterBarFacets as $group => $values)
        {
            foreach ($values['reset-url'] as $index => $url)
            {
                $this->createFilterWithRemoveX($values['reset-text'][$index], $url);
            }
        }
    }

    public function emitSearchFilters($resultControlsHtml)
    {
        $useElasticsearch = $this->searchResults->useElasticsearch();

        $request = Zend_Controller_Front::getInstance()->getRequest();
        $requestArray = $request->getParams();

        $this->getKeywordsArg($useElasticsearch);
        $this->getAdvancedSearchArgs($requestArray, $useElasticsearch);
        $this->getYearRangeArgs();

        $this->filterMessage .= __('You searched for: ');

        $this->emitBasicSearchFilters();
        $this->emitAdvancedSearchFilters();

        if ($useElasticsearch)
        {
            $this->emitElasticsearchFilters();
        }

        $html = $this->filterCount> 0 ? "<div id='search-filters-message'>$this->filterMessage</div>" : '';
        $html .= "<div id='search-selector-bar'>";
        $html .= "<div id='search-selectors'>{$resultControlsHtml}</div>";
        $html .= "</div>";
        return $html;
    }

    protected function getAdvancedSearchArgs(array $requestArray, $useElasticsearch)
    {
        if (!array_key_exists('advanced', $requestArray))
            return;

        $advancedIndex = 0;
        foreach ($requestArray['advanced'] as $i => $row)
        {
            if (empty($row['element_id']) || empty($row['type']))
            {
                continue;
            }

            $elementId = $row['element_id'];

            if (ctype_digit($elementId))
            {
                // The value is an Omeka element Id.
                $elementName = ItemMetadata::getElementNameFromId($elementId);
            }
            else
            {
                // The value is an Omeka element name.
                $elementName = $elementId;
            }

            if (empty($elementName))
            {
                continue;
            }

            $type = __($row['type']);
            $advancedValue = $elementName . ': ' . $type;
            if (isset($row['terms']) && $type != 'is empty' && $type != 'is not empty')
            {
                $terms = $row['terms'];

                // Put single quotes around the terms unless they are already wrapped in double quotes.
                $phraseMatch = strpos($terms, '"') === 0 && strrpos($terms, '"') === strlen($terms) - 1;
                if (!$phraseMatch)
                {
                    $terms = "'$terms'";
                }

                $advancedValue .= " $terms";
            }

            if ($advancedIndex && !$useElasticsearch)
            {
                if (isset($row['joiner']) && $row['joiner'] === 'or')
                {
                    $advancedValue = __('OR') . ' ' . $advancedValue;
                }
                else
                {
                    $advancedValue = __('AND') . ' ' . $advancedValue;
                }
            }

            $this->advancedArgsArray[$advancedIndex++] = $advancedValue;
        }
    }

    protected function getKeywordsArg($useElasticsearch)
    {
        $query = $this->searchResults->getKeywords();

        if (empty($query))
            return;

        // Derive the query arg name/value pair based on whether the keywords came from the
        // simple search textbox ('query') or the Advanced Search page keywords field ('keywords').
        $argName = isset($_GET['keywords']) ? 'keywords' : 'query';
        $this->basicArgsArray[$argName]['value'] = $query;

        $condition = $this->searchResults->getKeywordsCondition();
        $qualifier = '';

        if ($condition == SearchResultsView::KEYWORD_CONDITION_ALL_WORDS || $condition == SearchResultsView::KEYWORD_CONDITION_BOOLEAN)
        {
            $words = array_map('trim', explode(' ', $query));
            $keywords = '';

            foreach ($words as $word)
            {
                if (empty($word) || (!$useElasticsearch && SearchQueryBuilder::isStopWord($word)))
                    continue;

                if (!empty($keywords))
                    $keywords .= ' ';

                $keywords .= $word;
            }

            if (!$useElasticsearch)
            {
                $condition = strtolower($this->searchResults->getKeywordsConditionName());
                if ($this->searchResults->getSearchTitles())
                {
                    $condition .= __(' in titles only');
                }
                $qualifier = " ($condition)";
            }
        }
        else
        {
            $keywords = $query;
        }

        // Put single quotes around the keywords unless they are already wrapped in double quotes.
        $phraseMatch = strpos($keywords, '"') === 0 && strrpos($keywords, '"') === strlen($keywords) - 1;
        if (!$phraseMatch)
        {
            $keywords = "'$keywords'";
        }

        $this->basicArgsArray[$argName]['display'] = $keywords . $qualifier;
    }

    protected function getYearRangeArgs()
    {
        $yearStart = AvantCommon::queryStringArg('year_start', 0);
        $yearEnd = AvantCommon::queryStringArg('year_end', 0);

        if ($yearStart > 0)
        {
            $this->basicArgsArray['year_start']['value'] = $yearStart;
            $this->basicArgsArray['year_start']['display'] = __('Year start: ') . $yearStart;
        }

        if ($yearEnd > 0)
        {
            $this->basicArgsArray['year_end']['value'] = $yearEnd;
            $this->basicArgsArray['year_end']['display'] = __('Year end: ') . $yearEnd;
        }
    }
}