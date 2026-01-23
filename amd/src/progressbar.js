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
define(['jquery', 'core/ajax', 'core/log', 'core/templates', 'core/utils'],
    function($, Ajax, Log, Templates, Utils) {
        /**
         * Show progress event information for a cell.
         * @param {Event} event
         */
        function showInfo(event) {
            var cell = $(this);
            var container = cell.closest('.block_completion_progress .barContainer');
            var visibleinfo = container.siblings('.progressEventInfo:visible');
            var infotoshow = container.siblings('.progressEventInfo[data-inforef=' + cell.data('inforef') + ']');

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
            var infotoshow = container.siblings('.progressEventInfo[data-inforef=' + cell.data('inforef') + ']');
            var infolink = infotoshow.find('a').first();
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
            if (event.type == "keydown" && event.which != 13) {
                return;
            }
            var barrow = $(this).closest('.block_completion_progress .barContainer').find('.barRow');
            var cellswidth = barrow.find('.barRowCells').prop('clientWidth');
            var amount = event.data * cellswidth;

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

                rightarrow.toggleClass('active', (scrolled > threshold));
                leftarrow.toggleclass('active', (scrollWidth > threshold && scrolled < scrollWidth - threshold));
            } else {
                leftarrow.toggleClass('active', (scrolled > threshold));
                rightarrow.toggleClass('active', (scrollWidth > threshold && scrolled < scrollWidth - threshold));
            }
        }

        /**
         * Place the 'now' marker in the centre of the scrolled bar.
         * @param {jQuery} barel optional bar element. If not passed, all bars will be positioned.
         */
        function positionNow(barel) {
            var barrows;
            if (typeof barel !== 'undefined') {
                barrows = barel.find('.barRow');
            } else {
                barrows = $('.block_completion_progress .barRow');
            }
            var nowicons = barrows.find('.nowDiv .icon');

            var barcontainer = barrows.closest('.block_completion_progress .barContainer');
            var leftarrow = barcontainer.find('.left-arrow-svg');
            var rightarrow = barcontainer.find('.right-arrow-svg');
            leftarrow.css('display', 'block');
            rightarrow.css('display', 'block');

            nowicons.each(function() {
                var nowicon = $(this);
                var barrow = nowicon.closest('.block_completion_progress .barRow');
                var cellswidth = barrow.find('.barRowCells').prop('clientWidth');

                barrow.prop('scrollLeft', 0);
                barrow.prop('scrollLeft', nowicon.offset().left - barrow.offset().left -
                    cellswidth / 2);
            });
            barrows.each(checkArrows);
        }

        /**
         * Re-render the blocks which have a cell for the given cmid.
         * @param {String} cmid
         */
        function rerenderBlocksWithCmid(cmid) {
            var blocks = $('.block.block_completion_progress:has(.progressBarCell[data-inforef$="-' + cmid + '"])');
            blocks.each(function() {
                let block = $(this);
                let blockcontent = block.find('div.block_completion_progress');
                let barcontainer = block.find('.barContainer');
                let courseid = barcontainer.data('courseid');
                let instanceid = barcontainer.data('instanceid');
                let userid = barcontainer.data('userid');

                Log.debug(`block_completion_progress: reloading course ${courseid} blockinstance ${instanceid} user ${userid}`);
                Ajax.call(
                    [{
                        methodname: 'block_completion_progress_get_blockinstance_data',
                        args: {
                            courseid,
                            instanceid,
                            userid,
                        },
                        done: function(response) {
                            Templates.render('block_completion_progress/completion_progress', response)
                                .done(function(html, js) {
                                    let newcontent = Templates.replaceNode(blockcontent, html, js);
                                    positionNow($(newcontent[0]));
                                })
                                .fail(ex => Log.debug('block_completion_progress: error rendering template to reload ' +
                                    `course ${courseid} blockinstance ${instanceid} user ${userid} -- ${ex.errorcode}`));
                        },
                        fail: ex => Log.debug('block_completion_progress: error making ajax call to reload ' +
                            `course ${courseid} blockinstance ${instanceid} user ${userid} -- ${ex.errorcode}`),
                    }],
                    true, true, true
                );
            });
        }

        /**
         * Set up event handlers to drive all instances of progress bars which may exist.
         */
        function init() {
            // Show information elements on hover or tap.
            $(document.body).on('touchstart mouseover', '.block_completion_progress .progressBarCell', showInfo);

            // Navigate to the activity when its cell is clicked.
            $(document.body).on('click', '.block_completion_progress .progressBarCell[data-haslink=true]', viewActivity);

            // Show all information elements when the 'show all' link is clicked.
            $(document.body).on('click', '.block_completion_progress .progressShowAllInfo', showAllInfo);

            // Handle the presentation of scroll arrows when in scroll mode.
            document.addEventListener('scroll', function(e) {
                if (e.target.matches && e.target.matches('.block_completion_progress .barRow')) {
                    checkArrows.call(e.target);
                }
            }, true);
            $(document.body).on('click keydown', '.block_completion_progress .left-arrow-svg.active', -1, scrollContainer);
            $(document.body).on('click keydown', '.block_completion_progress .right-arrow-svg.active', 1, scrollContainer);
            $(window).resize(() => $('.block_completion_progress .barRow').each(checkArrows));
            $(document).on('theme_boost/drawers:shown theme_boost/drawers:hidden',
                Utils.debounce(() => $('.block_completion_progress .barRow').each(checkArrows), 250));

            // Handle the 'now' marker on page load and if a dynamic table updates.
            $(document).on('core_table/dynamic:tableContentRefreshed', function(e) {
                if (e.target.matches && e.target.matches('.table-dynamic[data-table-handler="overview"]' +
                        '[data-table-component="block_completion_progress"]')) {
                    positionNow();
                }
            });
            $(() => positionNow());

            // Rerender the block if a student manually completes an activity on the course page.
            $(document).on('core_course:manualcompletiontoggled', e => rerenderBlocksWithCmid(e.target.dataset.cmid));
        }

        return /** @alias module:block_completion_progress/progressbar */ {
            init: init
        };
    });
