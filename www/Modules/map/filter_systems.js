var SystemFilter = {

    filterKey: '',
    minDays: null,
    show_mid: true,
    show_other: true,
    show_hpint: true,
    show_errors: true,

    systems: [],
    fSystems: [],

    init: function (systems) {
        this.systems = systems;

        // Bind filter functions to preserve 'this' context
        this.filterNodes = this.filterNodes.bind(this);
        this.filterDays = this.filterDays.bind(this);
        this.filterMetering = this.filterMetering.bind(this);

    },

    applyFilters: function () {
        var filtered_nodes_days = this.systems.filter(this.filterNodes).filter(this.filterDays);
        this.fSystems = filtered_nodes_days.filter(this.filterMetering)
        // this.url_update();
    },

    filterNodes(row) {

        // empty the array storing filter query parts before parsing
        // this.filter_query_parts = [];

        if (this.filterKey != '') {
            if (this.filterKey === 'MID') {
                return row.mid_metering === 1;
            } else if (this.filterKey === 'HG' || this.filterKey === 'HeatGeek') {
                return row.heatgeek === 1;
            } else if (this.filterKey === 'NHG') {
                return row.heatgeek === 0;
            } else if (this.filterKey === 'UR') {
                return row.ultimaterenewables === 1;
            } else if (this.filterKey === 'HA') {
                return row.heatingacademy === 1;
            } else if (this.filterKey === 'HG4') {
                return row.heatgeek === 1 && row.combined_cop > 4; // Special filter
            } else {

                // if first part of this.filterKey is 'query' then format is query:field_name:value,field_name:value
                if (this.filterKey.indexOf('query') === 0) {
                    // remove 'query:' from start
                    var query = this.filterKey.substring(6);
                    var query_parts = query.split(',');

                    var result = true;

                    for (var i = 0; i < query_parts.length; i++) {
                        var query_part = query_parts[i].split(':');
                        var field = query_part[0];
                        var value = query_part[1];
                        var operator = query_part[2] || 'eq'; // default to 'eq' if no operator is provided
                        var enabled = query_part[3] || 't'; // default to 't' if no status is provided

                        // store the parsed query string
                        // this.filter_query_parts.push(this.deriveColumnValues({
                        //     field: field,
                        //     value: value,
                        //     operator: operator,
                        //     enabled: enabled === 't' ? true : false,
                        // }));

                        if (enabled === 'f') {
                            continue;
                        }

                        // if numeric check for exact match otherwise check for partial match
                        if (!isNaN(value)) {
                            // optional - select operator (e.g. gt, lt, gte, lte, ne), default is 'equals'
                            // the additional benefit of 'greater'/'less than' operators is that they can be used for specifying ranges of values
                            switch (operator) {
                                case 'gt':
                                    if (!(row[field] > value)) {
                                        result = false;
                                    }
                                    break;
                                case 'lt':
                                    if (!(row[field] < value)) {
                                        result = false;
                                    }
                                    break;
                                case 'gte':
                                    if (!(row[field] >= value)) {
                                        result = false;
                                    }
                                    break;
                                case 'lte':
                                    if (!(row[field] <= value)) {
                                        result = false;
                                    }
                                    break;
                                case 'ne':
                                    if (!(row[field] != value)) {
                                        result = false;
                                    }
                                    break;
                                default:
                                    // the default operator is 'equals', which is the original behaviour before adding support for other operators
                                    if (row[field] != value) {
                                        result = false;
                                    }
                            }
                            // if not numeric check for partial match                                                          
                        } else {
                            // default operator 'eq' means "contains", whereas 'ne' means "does not contain"
                            switch (operator) {
                                case 'ne':
                                    if (String(row[field]).toLowerCase().indexOf(value.toLowerCase()) != -1) {
                                        result = false;
                                    }
                                    break;
                                default:
                                    if (String(row[field]).toLowerCase().indexOf(value.toLowerCase()) == -1) {
                                        result = false;
                                    }
                            }
                        }
                    }
                    return result;
                    // search all fields and check if any contains the searched value (filterKey)
                } else {
                    return Object.keys(row).some((key) => {
                        return String(row[key]).toLowerCase().indexOf(this.filterKey.toLowerCase()) > -1
                    })
                }
            }
        }
        return true;
    },

    filterDays(row) {
        if (this.minDays == null || this.minDays == '' || isNaN(this.minDays)) this.minDays = 0;
        this.minDays = parseInt(this.minDays);
        let minDays = this.minDays - 1;
        if (minDays < 0) minDays = 0;
        return (row.combined_data_length / (24 * 3600)) >= minDays;
    },

    filterMetering(row) {

        var show = false;

        if (this.show_mid && row.mid_metering) {
            show = true;
        }
        if (this.show_other && !row.mid_metering && row.heat_meter != 'Heat pump integration') {
            show = true;
        }
        if (this.show_hpint && !row.mid_metering && row.heat_meter == 'Heat pump integration') {
            show = true;
        }


        if (this.show_errors && row.data_flag) {
            show = true;
        } else {
            if (row.data_flag) {
                show = false;
            }
        }
        return show;
    }
}