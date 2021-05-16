<template>
    <div id="chart" ref="chart"></div>
</template>

<script>
import lightweightChart from '../lib/chart.js';

export default {
    props: {
        chartData: Object
    },
    components: {
        lightweightChart
    },
    data() {
        return {
            candleSeries: null,
            lineSeriesEma1: null,
            lineSeriesEma2: null
        }
    },
    mounted() {
        this.initChart()
    },
    methods: {
        resize(width, height) {
            if (!width || !height) {
                return
            }
            this.chart.resize(width, height);
        },
        initChart() {

            console.info(this.chartData)

            this.chart = lightweightChart("chart", {
                width: this.$refs.chart.innerWidth,
                height: this.$refs.chart.innerHeight
            })

            this.candleSeries = this.chart.addCandlestickSeries();

            this.lineSeriesEma1 = this.chart.addLineSeries({
                lastValueVisible: false,
                crosshairMarkerVisible: false,
                color: 'rgb(4,162,40)',
                lineWidth: 1,
            });

            this.lineSeriesEma2 = this.chart.addLineSeries({
                lastValueVisible: false,
                crosshairMarkerVisible: false,
                color: 'rgb(219,11,46)',
                lineWidth: 1,
            });

            // resize observer (native JS)
            const ro = new ResizeObserver((entries) => {
                const cr = entries[0].contentRect;
                this.resize(cr.width, cr.height);
            });

            ro.observe(this.$refs.chart);

            window.addEventListener("resize", () => {
                this.resize(this.$refs.chart.innerWidth, this.$refs.chart.innerHeight);
            });


            this.candleSeries.setData(this.chartData.klines)
            this.lineSeriesEma1.setData(this.chartData.emas.ema1)
            this.lineSeriesEma2.setData(this.chartData.emas.ema2)


            // markers
            if (this.chartData.markers.length) {
                console.info(this.chartData.markers)
                let buy = {time: null, position: 'belowBar', color: '#01A500', shape: 'arrowUp', text: 'Buy'}
                let sell = {time: null, position: 'aboveBar', color: '#e91e63', shape: 'arrowDown', text: 'Sell'}

                let allMarkers = []

                this.chartData.markers.forEach(m => {
                    if (m.action === 'BUY') {
                        allMarkers.push(Object.assign({}, buy, {time: m.time}))
                    } else {
                        allMarkers.push(Object.assign({}, sell, {time: m.time}))
                    }
                })

                console.info(allMarkers)
                setTimeout(() => {
                    this.candleSeries.setMarkers(allMarkers)
                }, 2000);
            }
        }
    }
}
</script>


<style lang="scss">
#chart {
    min-height: 600px;
}
</style>
