// import Vuex from 'vuex'
// import storeData from "../store/backtest"
// import DateRangePicker from 'vue-mj-daterangepicker'
// import 'vue-mj-daterangepicker/dist/vue-mj-daterangepicker.css'

// Vue.use(DateRangePicker)
// Vue.use(Vuex)
// const store = new Vuex.Store(
//     storeData
// )


Vue.component('chart-component', require('../components/ChartComponent.vue').default);

const app = new Vue({
    el: '#app'
    // ,
    // store
});

