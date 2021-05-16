import {createChart, CrosshairMode} from 'lightweight-charts';

export default function (elementId, config={}) {
    let options = {
        timeScale: {
            timeVisible: true,
            secondsVisible: true,
            visible: true
        },
        localization: {},
        // width: 600,
        // height: 600,
        crosshair: {
            mode: CrosshairMode.Normal,
        }
    }

    options = Object.assign({}, options, config)
    return createChart(document.getElementById(elementId), options);
};
