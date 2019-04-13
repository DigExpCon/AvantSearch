<?php
$avantElasticsearchFacets = new AvantElasticsearchFacets();
$facetNames = $avantElasticsearchFacets->getFacetNames();
$queryString = $avantElasticsearchFacets->createQueryStringWithFacets($query);
$appliedFacets = $query['facet'];
$findUrl = get_view()->url('/find');
$appliedFacetValues = array();
?>

<?php if (count($appliedFacets) > 0): ?>
    <div id="elasticsearch-filters-active">
        <div class="elasticsearch-facet-section">Applied Filters
            <a class="elasticsearch-facet-reset"
               href="<?php echo get_view()->url('/find') . '?query=' . urlencode($query['query']); ?>">[Reset]</a>
        </div>
        <?php
        $appliedFilters = '';

        foreach ($appliedFacets as $facetName => $facetValues)
        {
            $facetLabel = htmlspecialchars($facetNames[$facetName]);
            $appliedFilters .= '<div class="elasticsearch-facet-name">' . $facetLabel . '</div>';
            $appliedFilters .= '<ul>';

            foreach ($facetValues as $facetValue)
            {
                $appliedFacetValues[] = $facetValue;
                $resetLink = $avantElasticsearchFacets->createRemoveFacetLink($queryString, $facetName, $facetValue);
                $appliedFilters .= '<li>';
                $appliedFilters .= "<i>$facetValue</i>";
                $appliedFilters .= '<a href="' . $findUrl . '?' . $resetLink . '"> [&#10006;]</a>';
                $appliedFilters .= '</li>';
            }

            $appliedFilters .= '</ul>';
        }
        echo $appliedFilters;
        ?>
    </div>
<?php endif; ?>

<div id="elasticsearch-filters">
    <div class="elasticsearch-facet-section">Filters</div>
    <?php
    foreach ($facetNames as $facetName => $facetLabel)
    {
        if ($facetName == 'tag')
        {
            // The tag facet is fully supported, but for now simply don't show it.
            continue;
        }

        if (count($aggregations[$facetName]['buckets']) == 0)
        {
            continue;
        }

        echo '<div class="elasticsearch-facet-name">' . $facetLabel . '</div>';

        $filters = '';
        $buckets = $aggregations[$facetName]['buckets'];

        foreach ($aggregations[$facetName]['buckets'] as $bucket)
        {
            $bucketValue = $bucket['key'];
            $applied = in_array($bucketValue, $appliedFacetValues);
            $text = htmlspecialchars($bucketValue);
            $count = ' (' . $bucket['doc_count'] . ')';

            if ($applied)
            {
                // Don't provide a link for a facet that's already been applied.
                $filter = $text . $count;
            }
            else
            {
                // Create a link that the user can click to apply this facet.
                $filterLink = $avantElasticsearchFacets->createAddFacetLink($queryString, $facetName, $bucketValue);
                $facetUrl = $findUrl . '?' . $filterLink;
                $filter = '<a href="' . $facetUrl . '">' . $text . '</a>' . $count;
            }

            $filters .= "<li>$filter</li>";
        }

        echo "<ul>$filters</ul>";
    }
    ?>
</div>

