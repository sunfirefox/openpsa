var openpsa_calendar_widget =
{
    prepare_toolbar_buttons: function(selector, prefix, settings)
    {
        $('#openpsa_calendar_add_event').on('click', function(event)
        {
            event.preventDefault();
            var date = $(selector).fullCalendar('getDate'),
            url = prefix + 'event/new/' + (Math.round(date.getTime() / 1000) + 1) + '/',
            window_options = "toolbar=0,location=0,status=0,height=" + settings.height + ",width=" + settings.width + ",dependent=1,alwaysRaised=1,scrollbars=1,resizable=1";

            window.open(url, 'new_event', window_options);
        });
        $("#date-navigation").parent().bind("click", function(event)
        {
            if (   $(event.target).parent().attr('id') !== 'date-navigation'
                && $(event.target).attr('id') !== 'date-navigation')
            {
                //don't fire on datepicker navigation clicks
                return;
            }
            if ($(this).hasClass("active"))
            {
                $(this).removeClass("active");
                $("#date-navigation-widget").hide();
            }
            else if ($(this).hasClass("initialized"))
            {
                $("#date-navigation-widget").show();
                $(this).addClass("active");
            }
            else
            {
                $("#date-navigation").append("<div id=\"date-navigation-widget\"></div>");
                $("#date-navigation-widget").css("position", "absolute");
                $("#date-navigation-widget").css("z-index", "1000");
                var default_date = $(selector).fullCalendar('getDate');
                $("#date-navigation-widget").datepicker(
                {
                    dateFormat: "yy-mm-dd",
                    defaultDate: default_date,
                    prevText: "",
                    nextText: "",
                    onSelect: function(dateText, inst)
                    {
                        var date = $.fullCalendar.parseDate(dateText);
                        $(selector).fullCalendar('gotoDate', date);

                        $("#date-navigation").parent().removeClass("active");
                        $("#date-navigation-widget").hide();
                    }
                });
                $(this).addClass("active");
                $(this).addClass("initialized");
            }
        });
    },
    parse_url: function(prefix)
    {
        var args = location.pathname.substr(prefix.length).split('/'),
        settings = {},
        date;
        if (args[0] !== undefined)
        {
            switch (args[0])
            {
                case 'month':
                case 'basicDay':
                case 'basicWeek':
                case 'agendaDay':
                case 'agendaWeek':
                    settings.defaultView = args[0];
                    break;
            }
        }
        if (args[1] !== undefined)
        {
            date = new Date(Date.parse(args[1]));
            settings.year = date.getFullYear();
            settings.month = date.getMonth();
            settings.date = date.getDate();
        }
        return settings;
    },
    update_url: function(selector, prefix)
    {
        var last_state = History.getState(),
        view = $(selector).fullCalendar('getView'),
        state_data =
        {
            date: $.fullCalendar.formatDate($(selector).fullCalendar('getDate'), 'yyyy-MM-dd'),
            view: view.name
        },
        new_url = prefix + view.name + '/' + state_data.date + '/';
        // skip if the last state was same than current
        if (last_state.url === new_url)
        {
            return;
        }

        History.pushState(state_data, view.title + ' ' + $('body').data('title'), new_url);
    },
    set_height: function(selector)
    {
        var new_height = $('#content-text').height();

        if (new_height !== $(selector).height())
        {
            $(selector).fullCalendar('option', 'height', new_height);
        }
    },
    initialize: function(selector, prefix, settings)
    {
        var defaults =
        {
            theme: true,
            defaultView: "month",
            weekNumbers: true,
            weekMode: 'liquid',
            firstHour: 8,
            ignoreTimezone: false,
            header:
            {
                left: 'month,agendaWeek,agendaDay',
                center: 'title',
                right: 'today prev,next'
            },
            events: function (start, end, callback) {
                $.ajax({
                    url: prefix + 'json/',
                    dataType: 'json',
                    data: {
                        start: Math.round(start.getTime() / 1000),
                        end: Math.round(end.getTime() / 1000)
                    },
                    success: function (events) {
                        callback(events);
                    }
                });
            },
            viewRender: function (view, element)
            {
                openpsa_calendar_widget.update_url(selector, prefix);
            },
            eventRender: function (event, element) {
                element.find('.fc-event-inner').append('<span class="participants">(' + event.participants.join(', ') + ')</span>');
            },
            eventClick: function (calEvent, jsEvent, view) {
                var guid = calEvent.id,
                url = prefix + 'event/' + guid + '/',
                window_options = "toolbar=0,location=0,status=0,height=" + settings.height + ",width=" + settings.width + ",dependent=1,alwaysRaised=1,scrollbars=1,resizable=1";

                jsEvent.preventDefault();

                window.open(url, 'event' + guid, window_options);
            },
            dayClick: function (date, allDay, jsEvent, view)
            {
                var url = prefix + 'event/new/' + (Math.round(date.getTime() / 1000) + 1) + '/',
                window_options = "toolbar=0,location=0,status=0,height=" + settings.height + ",width=" + settings.width + ",dependent=1,alwaysRaised=1,scrollbars=1,resizable=1";

                jsEvent.preventDefault();
                window.open(url, 'event_new', window_options);
            }
        };

        settings = $.extend({}, defaults, openpsa_calendar_widget.parse_url(prefix), openpsa_calendar_widget.localize(), settings || {});

        $(selector).fullCalendar(settings);
        openpsa_calendar_widget.prepare_toolbar_buttons(selector, prefix, settings);

        $('body').data('title', document.title);

        // Prepare History.js
        if ( History.enabled ) {
            History.Adapter.bind(window, 'statechange', function(){
                var State = History.getState();
                $(selector).fullCalendar('gotoDate', $.fullCalendar.parseDate(State.data.date));
                $(selector).fullCalendar('changeView', State.data.view);
            });
        }

        org_openpsa_resizers.append_handler('calendar', function()
        {
            openpsa_calendar_widget.set_height(selector);
        });
    },
    // this is a workaround until fullCalendar gets proper l10n support
    localize: function()
    {
        var locale_id,
        locale_data,
        settings = {};

        for (locale_id in $.datepicker.regional)
        {
            // for some reason, this awkward iteration is the only way
            // to get the "array keys"
            locale_data = $.datepicker.regional[locale_id];
        }

        settings.dayNames = locale_data.dayNames;
        settings.dayNamesShort = locale_data.dayNamesShort;
        settings.monthNames = locale_data.monthNames;
        settings.monthNamesShort = locale_data.monthNamesShort;
        settings.firstDay = locale_data.firstDay;
        settings.weekNumberTitle = locale_data.weekHeader;

        return settings;
    }
};