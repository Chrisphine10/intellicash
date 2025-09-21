(function ($) {
    "use strict";

    $(window).on('load', function() {
        setTimeout(function() {

            if (document.getElementById("signUpAnalysis")) {
                var chartCurrency = _currency_symbol;
                const signUpAnalysis = document
                    .getElementById("signUpAnalysis")
                    .getContext("2d");
                var signUpAnalysisChart = new Chart(signUpAnalysis, {
                    type: "bar",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                data: [],
                                backgroundColor: ["rgba(72, 52, 212, 0.6)"],
                                borderColor: ["rgba(72, 52, 212, 0.8)"],
                                yAxisID: "y",
                                borderWidth: 2,
                                tension: 0.4,
                                borderRadius: {
                                    topLeft: 10,
                                    topRight: 10,
                                    bottomLeft: 0,
                                    bottomRight: 0
                                },
                            },
                        ],
                    },
                    options: {
                        interaction: {
                            mode: "index",
                            intersect: false,
                        },
                        responsive: true,
                        stacked: true,
                        scales: {
                            y: {
                                type: "linear",
                                display: true,
                                position: "left",
                                ticks: {
                                    //stepSize: 1,
                                    precision: 0,
                                },
                            },
                        },
                        plugins: {
                            legend: {
                                display: false,
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: "rectRounded",
                                },
                            },
                        },
                    },
                });
            }

            if (document.getElementById("revenueAnalysis")) {
                var chartCurrency = _currency_symbol;
                const revenueAnalysis = document
                    .getElementById("revenueAnalysis")
                    .getContext("2d");
                var revenueAnalysisChart = new Chart(revenueAnalysis, {
                    type: "line",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: $lang_revenue,
                                data: [],
                                backgroundColor: ["rgba(46, 204, 113, 0.4)"],
                                borderColor: ["rgba(46, 204, 113, 1.0)"],
                                yAxisID: "y",
                                borderWidth: 2,
                                tension: 0.4,
                            },
                        ],
                    },
                    options: {
                        interaction: {
                            mode: "index",
                            intersect: false,
                        },
                        responsive: true,
                        //maintainAspectRatio: false,
                        stacked: true,
                        scales: {
                            y: {
                                type: "linear",
                                display: true,
                                position: "left",
                                ticks: {
                                    callback: function (value, index, values) {
                                        return chartCurrency + " " + value;
                                    },
                                },
                            },
                        },
                        plugins: {
                            legend: {
                                display: false,
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: "rectRounded",
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        var label = context.dataset.label || "";

                                        if (
                                            context.parsed.y !== null &&
                                            context.dataset.yAxisID == "y"
                                        ) {
                                            label +=
                                                ": " +
                                                chartCurrency +
                                                " " +
                                                context.parsed.y;
                                        } else {
                                            label += ": " + context.parsed.y;
                                        }

                                        return label;
                                    },
                                },
                            },
                        },
                    },
                });
            }

            //Package Overview Chart
            if (document.getElementById("packageOverview")) {
                var link2 = _url + "/admin/dashboard/json_package_wise_subscription";
                $.ajax({
                    url: link2,
                    success: function (data2) {
                        var json2 = JSON.parse(data2);

                        const ctx = document
                            .getElementById("packageOverview")
                            .getContext("2d");
                        const packageOverviewChart = new Chart(ctx, {
                            type: "doughnut",
                            data: {
                                labels: json2["package"],
                                datasets: [
                                    {
                                        data: json2["subscribed"],
                                        backgroundColor: json2["colors"],
                                    },
                                ],
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        labels: {
                                            usePointStyle: true,
                                            pointStyle: "rectRounded",
                                        },
                                    },
                                    title: {
                                        display: false,
                                        text: $lang_expense_overview,
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function (context) {
                                                return (
                                                    " " +
                                                    context.label +
                                                    ": " +
                                                    context.parsed
                                                );
                                            },
                                        },
                                    },
                                },
                            },
                        });
                    },
                });
            }

            if (document.getElementById("signUpAnalysis")) {
                $.ajax({
                    url: _url + "/admin/dashboard/json_yearly_signup",
                    success: function (data) {
                        var json = JSON.parse(data);

                        signUpAnalysisChart.data.labels = json["month"];
                        signUpAnalysisChart.data.datasets[0].data = [
                            typeof json["signups"][1] !== "undefined"
                                ? json["signups"][1]
                                : 0,
                            typeof json["signups"][2] !== "undefined"
                                ? json["signups"][2]
                                : 0,
                            typeof json["signups"][3] !== "undefined"
                                ? json["signups"][3]
                                : 0,
                            typeof json["signups"][4] !== "undefined"
                                ? json["signups"][4]
                                : 0,
                            typeof json["signups"][5] !== "undefined"
                                ? json["signups"][5]
                                : 0,
                            typeof json["signups"][6] !== "undefined"
                                ? json["signups"][6]
                                : 0,
                            typeof json["signups"][7] !== "undefined"
                                ? json["signups"][7]
                                : 0,
                            typeof json["signups"][8] !== "undefined"
                                ? json["signups"][8]
                                : 0,
                            typeof json["signups"][9] !== "undefined"
                                ? json["signups"][9]
                                : 0,
                            typeof json["signups"][10] !== "undefined"
                                ? json["signups"][10]
                                : 0,
                            typeof json["signups"][11] !== "undefined"
                                ? json["signups"][11]
                                : 0,
                            typeof json["signups"][12] !== "undefined"
                                ? json["signups"][12]
                                : 0,
                        ];
                        signUpAnalysisChart.update();
                    },
                });
            }

            if (document.getElementById("revenueAnalysis")) {
                $.ajax({
                    url: _url + "/admin/dashboard/json_yearly_revenue",
                    success: function (data) {
                        var json = JSON.parse(data);

                        revenueAnalysisChart.data.labels = json["month"];
                        revenueAnalysisChart.data.datasets[0].data = [
                            typeof json["transactions"][1] !== "undefined"
                                ? json["transactions"][1]
                                : 0,
                            typeof json["transactions"][2] !== "undefined"
                                ? json["transactions"][2]
                                : 0,
                            typeof json["transactions"][3] !== "undefined"
                                ? json["transactions"][3]
                                : 0,
                            typeof json["transactions"][4] !== "undefined"
                                ? json["transactions"][4]
                                : 0,
                            typeof json["transactions"][5] !== "undefined"
                                ? json["transactions"][5]
                                : 0,
                            typeof json["transactions"][6] !== "undefined"
                                ? json["transactions"][6]
                                : 0,
                            typeof json["transactions"][7] !== "undefined"
                                ? json["transactions"][7]
                                : 0,
                            typeof json["transactions"][8] !== "undefined"
                                ? json["transactions"][8]
                                : 0,
                            typeof json["transactions"][9] !== "undefined"
                                ? json["transactions"][9]
                                : 0,
                            typeof json["transactions"][10] !== "undefined"
                                ? json["transactions"][10]
                                : 0,
                            typeof json["transactions"][11] !== "undefined"
                                ? json["transactions"][11]
                                : 0,
                            typeof json["transactions"][12] !== "undefined"
                                ? json["transactions"][12]
                                : 0,
                        ];
                        revenueAnalysisChart.update();
                    },
                });
            }
            $(".loading-chart").remove();
       }, 2000);
    });
})(jQuery);
