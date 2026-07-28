// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Overview table filter.
 *
 * @module  block_completion_progress/overview_filter
 * @copyright 2021 Tomo Tsuyuki <tomotsuyuki@catalyst-au.net>
 * @copyright 2025 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CourseFilter from 'core/datafilter/filtertypes/courseid';
import DataFilter from 'core/datafilter';
import DataFilterSelectors from 'core/datafilter/selectors';
import * as DynamicTable from 'core_table/dynamic';
import Notification from 'core/notification';
import BlockInstanceFilter from 'block_completion_progress/datafilter/filtertypes/blockinstanceid';

/**
 * Initialise the overview table filter.
 * @param {String} filterRegionId The id of the filter element.
 * @param {Object} initialFilters Initial filters configuration.
 */
export const init = async(filterRegionId, initialFilters) => {
    const filterSet = document.querySelector(`#${filterRegionId}`);

    const applyFilters = (filters, pendingPromise) => {
        DynamicTable
            .setFilters(
                DynamicTable.getTableFromId(filterSet.dataset.tableRegion),
                {
                    jointype: parseInt(filterSet.querySelector(DataFilterSelectors.filterset.fields.join).value, 10),
                    filters,
                }
            )
            .then(result => {
                pendingPromise.resolve();
                const url = new URL(window.location.href);
                url.searchParams.set('filterset', result.dataset.tableFilters);
                history.replaceState({}, '', url.toString());
                return result;
            })
            .catch(Notification.exception);
    };

    const dataFilter = new DataFilter(filterSet, applyFilters);
    dataFilter.activeFilters.courseid = new CourseFilter('courseid', filterSet);
    dataFilter.activeFilters.blockinstanceid = new BlockInstanceFilter('blockinstanceid', filterSet,
        initialFilters.filters.blockinstanceid.values[0]);
    dataFilter.init();

    // This line does not update the overall any/all/none UI control and I do not understand why, yet. Very little of
    // the promise acrobatics in user/amd/src/participants_filter.js makes sense frankly, but the code of
    // question/amd/src/filter.js is a bit more comprehensible.
    filterSet.querySelector(DataFilterSelectors.filterset.fields.join).value = initialFilters.jointype;

    let emptyFilterRow = filterSet.querySelector(DataFilterSelectors.filterset.regions.emptyFilterRow);
    let rownum = 1;
    for (let f in initialFilters.filters) {
        let fil = initialFilters.filters[f];
        switch (fil.name) {
            case 'courseid':
            case 'blockinstanceid':
                break;
            default:
                if (emptyFilterRow) {
                    emptyFilterRow.remove();
                    emptyFilterRow = undefined;
                }
                dataFilter.addFilterRow({
                    filtertype: fil.name,
                    values: fil.values,
                    jointype: fil.jointype,
                    rownum: rownum,
                });
                rownum++;
                break;
        }
    }
};
