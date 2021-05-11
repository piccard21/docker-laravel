<template>
    <div
        v-show="visible"
        class="loading-mask"
    >
        <span><b-spinner v-if="true" variant="light"></b-spinner></span>
        <span v-show="text" class="ml-2">{{text}}</span>
    </div>
</template>

<script>
export default {
    name: "LoadingComponent",

    data() {
        return {
            visible: false,
            text: null
        }
    },
    methods: {
        setText (text) {
            this.text = text
        }
    }

}
</script>

<style lang="scss">
.loading-mask {
    position: absolute;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10;
    margin: 0;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    color: #fff;
    background-color: #53a1ef;
    opacity: .5;
}
</style>
