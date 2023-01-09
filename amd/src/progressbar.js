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
 * Completion Progress block progress bar behaviour.
 *
 * @module     block_completion_progress/progressbar
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/pubsub', 'core/utils'],
    function($, PubSub, Utils) {
        /**
         * Show progress event information for a cell.
         * @param {Event} event
         */
        function showInfo(event) {
            var cell = $(this);
            var container = cell.closest('.block_completion_progress .barContainer');
            var visibleinfo = container.siblings('.progressEventInfo:visible');
            var infotoshow = container.siblings('#' + cell.data('infoRef'));

            if (!visibleinfo.is(infotoshow)) {
                visibleinfo.hide();
                infotoshow.show();
            }

            event.preventDefault();
        }

        /**
         * Show all progress event information (for accessibility).
         * @param {Event} event
         */
        function showAllInfo(event) {
            var initialinfo = $(this).closest('.progressEventInfo');

            initialinfo.siblings('.progressEventInfo').show();
            initialinfo.hide();

            event.preventDefault();
        }

        /**
         * Navigate to a cell's activity location.
         */
        function viewActivity() {
            var cell = $(this);
            var container = cell.closest('.block_completion_progress .barContainer');
            var infotoshow = container.siblings('#' + cell.data('infoRef'));
            var infolink = infotoshow.find('a.action_link');
            if (infolink.prop('onclick') !== null) {
                infolink.click();
            } else {
                document.location = infolink.prop('href');
            }
        }

        /**
         * Scroll the bar corresponding to the arrow clicked.
         * @param {Event} event
         */
        function scrollContainer(event) {
            var barrow = $(this).closest('.block_completion_progress .barContainer').find('.barRow');
            var amount = event.data * barrow.prop('scrollWidth') * 0.15;

            barrow.prop('scrollLeft', barrow.prop('scrollLeft') + amount);

            event.preventDefault();
        }

        /**
         * Show or hide the scroll arrows based on the visible position.
         */
        function checkArrows() {
            var barrow = $(this);
            var barcontainer = barrow.closest('.block_completion_progress .barContainer');
            var leftarrow = barcontainer.find('.left-arrow-svg');
            var rightarrow = barcontainer.find('.right-arrow-svg');
            var scrolled = barrow.prop('scrollLeft');
            var scrollWidth = barrow.prop('scrollWidth') - barrow.prop('offsetWidth');
            var threshold = Math.floor(barrow.find('.progressBarCell:first-child').width() * 0.25);

            if (document.dir === 'rtl') {
                scrolled = -scrolled;

                if (scrolled > threshold) {
                    rightarrow.css('display', 'block');
                } else {
                    rightarrow.css('display', 'none');
                }
                if (scrollWidth > threshold && scrolled < scrollWidth - threshold) {
                    leftarrow.css('display', 'block');
                } else {
                    leftarrow.css('display', 'none');
                }
            } else {
                if (scrolled > threshold) {
                    leftarrow.css('display', 'block');
                } else {
                    leftarrow.css('display', 'none');
                }
                if (scrollWidth > threshold && scrolled < scrollWidth - threshold) {
                    rightarrow.css('display', 'block');
                } else {
                    rightarrow.css('display', 'none');
                }
            }
        }

        /**
         * Prepare scroll mode behaviour.
         * @param {jQuery} barcontainers there could be many nodes here in overview mode
         */
        function setupScroll(barcontainers) {
            var barrows = barcontainers.find('.barRow');

            /**
             * Check arrow visibility for each of the bar rows.
             */
            function checkEachBar() {
                barrows.each(checkArrows);
            }

            barrows.scroll(checkArrows);
            $(window).resize(checkEachBar);
            PubSub.subscribe('nav-drawer-toggle-end', checkEachBar); // Boost ≤3.11.
            $(document).on('theme_boost/drawers:shown theme_boost/drawers:hidden',
                Utils.debounce(checkEachBar, 250)); // Boost ≥4.0.

            // On page load, place the 'now' marker in the centre of the scrolled bar
            // and adjust which arrows should be visible.
            $(function() {
                var nowicons = barcontainers.find('.nowicon');
                nowicons.each(function() {
                    var nowicon = $(this);
                    var barrow = nowicon.closest('.block_completion_progress .barRow');

                    barrow.prop('scrollLeft', 0);
                    barrow.prop('scrollLeft', nowicon.offset().left - barrow.offset().left -
                        barrow.width() / 2);
                });

                barrows.each(checkArrows);
            });

            barcontainers.on('click', '.left-arrow-svg', -1, scrollContainer);
            barcontainers.on('click', '.right-arrow-svg', 1, scrollContainer);
        }

        /**
         * Set up event handlers for a particular progress bar instance.
         * @param {integer} instanceid the bar instance id
         */
        function initialiseBar(instanceid) {
            var barcontainers = $('.block_completion_progress ' +
                '.barContainer[data-instanceid="' + instanceid + '"]');

            // Show information elements on hover or tap.
            barcontainers.on('touchstart mouseover', '.progressBarCell', showInfo);

            // Navigate to the activity when its cell is clicked.
            barcontainers.on('click', '.progressBarCell[data-haslink=true]', viewActivity);

            // Show all information elements when the 'show all' link is clicked.
            barcontainers.siblings('.progressEventInfo').find('.progressShowAllInfo').click(showAllInfo);

            setupScroll(barcontainers);
        }

        return /** @alias module:block_completion_progress/progressbar */ {
            /**
             * Initialise progress bar instances.
             * @param {array} instanceids an array of progress bar instance ids
             */
            init: function(instanceids) {
                for (var i = instanceids.length - 1; i >= 0; i--) {
                    initialiseBar(instanceids[i]);
                }
            },
        };
    });
