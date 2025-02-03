<?php
/* @var $searchResults SearchResultsTableView */

$data = new SearchResultsTableViewRowData($item, $searchResults);
$columnData = $searchResults->getColumnsData();
$layoutData = $searchResults->getLayoutsData();

echo '<div class="table-row-start">';
echo '<div class="item-linked-details">';
// Emit the columns for this row's data.
foreach ($columnData as $elementId => $column)
{
    $columnName = $column['name'];

    // Form the special class name e.g. 'search-td-title' that is unique to this row column.
    $columnClass = SearchResultsView::createColumnClass($columnName, 'td');

    // Get this row's column text.
    $text = $data->elementValue[$columnName]['text'];

    // Get the layout classes for this element name e.g. 'L2 L7'.
    $classes = SearchResultsTableView::createLayoutClasses($column);

    if (!empty(($classes)))
    {
        $columnHtml = "<td class=\"search-result $columnClass $classes\">$text</td>";
        echo $columnHtml;
    }
}
echo '</div>';

if (!$searchResults->hasLayoutL1())
{
    // The admin did not configure an L1 layout.
    echo '</div>';
    return;
}

// The code that follows emits the L1 Detail layout which is a table a column of the overall layout table.
?>

<div class="search-td-image L1">
    <?php echo $data->itemThumbnailHtml; ?>
</div>

<div class="search-td-title-detail L1">
    <div class="search-result-title">
        <?php echo $data->elementValue['Title']['text']; ?>
    </div>
    <div class="search-results-detail-table">
        <div class="search-results-detail-row">
            <?php if (!empty($column1)): ?>
            <div class="search-results-detail-col1">
                <?php
                foreach ($column1 as $elementName)
                {
                    $text = SearchResultsTableViewRowData::getElementDetail($data, $elementName);
                    echo "<div>$text</div>";
                }

                if (is_allowed($item, 'edit'))
                {
                    echo '<div class="search-results-edit"><a href="' . admin_url('/items/edit/' . $item->id) . '">' . __('Edit') . '</a></div>';
                }
                ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($column2)): ?>
            <div class="search-results-detail-col2">
                <?php
                foreach ($column2 as $elementName)
                {
                    $text = SearchResultsTableViewRowData::getElementDetail($data, $elementName);
                    echo "<div>$text</div>";
                }
                ?>
            </div>
            <?php endif; ?>
            <?php if (isset($data->elementValue['Description']['detail'])): ?>
            <div class="search-results-detail-col3">
                <div>
                    <?php echo $text = $data->elementValue['Description']['detail']; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
echo '</div>';
?>
