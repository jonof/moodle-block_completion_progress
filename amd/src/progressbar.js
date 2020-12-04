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
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'],
    function($) {
        /**
         * Show progress event information for a cell.
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
            document.location = infolink.prop('href');
        }

        /**
         * Prepare scroll mode behaviour.
         * @param {jQuery} barcontainer
         */
        function setupScroll(barcontainer) {
            var nowicon = barcontainer.find('.nowicon');
            var leftarrow = barcontainer.find('.left-arrow-svg');
            var rightarrow = barcontainer.find('.right-arrow-svg');

            /**
             * Positions the scroll arrows.
             */
            function checkArrows() {
                var threshold = 10, buffer = 5;
                var scrolled = barcontainer.prop('scrollLeft');
                var scrollWidth = barcontainer.prop('scrollWidth') - barcontainer.prop('offsetWidth');

                if (scrolled > threshold) {
                    leftarrow.css({
                        'display': 'block',
                        'left': (scrolled + buffer) + 'px'
                    });
                } else {
                    leftarrow.css('display', 'none');
                }
                if (scrollWidth > threshold && scrolled < scrollWidth - threshold) {
                    rightarrow.css({
                        'display': 'block',
                        'right': (buffer - scrolled) + 'px'
                    });
                } else {
                    rightarrow.css('display', 'none');
                }
            }

            /**
             * Scroll the bar.
             * @param {Event} event
             */
            function scrollContainer(event) {
                var amount = event.data * barcontainer.prop('scrollWidth') * 0.15;

                barcontainer.prop('scrollLeft', barcontainer.prop('scrollLeft') + amount);
                checkArrows();

                event.preventDefault();
            }

            if (nowicon.length > 0) {
                // Place the 'now' marker in the centre of the scrolled bar.
                barcontainer.prop('scrollLeft', 0);
                barcontainer.prop('scrollLeft', nowicon.offset().left
                    - barcontainer.offset().left - barcontainer.width() / 2);
            }

            leftarrow.click(-1, scrollContainer);
            rightarrow.click(1, scrollContainer);
            $(window).resize(checkArrows);
            checkArrows();
        }

        /**
         * Set up event handlers for a particular progress bar instance.
         * @param {integer} instanceid the bar instance id
         */
        function initialiseBar(instanceid) {
            var barcontainer = $('.block_completion_progress ' +
                '.barContainer[data-instanceid="' + instanceid + '"]');

            // Show information elements on hover or tap.
            barcontainer.on('touchstart mouseover', '.progressBarCell', showInfo);

            // Navigate to the activity when its cell is clicked.
            barcontainer.on('click', '.progressBarCell[data-haslink=true]', viewActivity);

            // Show all information elements when the 'show all' link is clicked.
            barcontainer.siblings('.progressEventInfo').find('.progressShowAllInfo').click(showAllInfo);

            setupScroll(barcontainer);
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
