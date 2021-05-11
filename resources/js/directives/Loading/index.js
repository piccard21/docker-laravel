import Loading from './Loading'

const toggleLoading = (el, binding) => {
    if (binding.value) {
        Vue.nextTick(() => {
            insertDom(el, el, binding)
            el.classList.add('has-loading-mask')
        })
    } else {
        Vue.nextTick(() => {
            el.domVisible = false
            el.instance.visible = false
            el.classList.remove('has-loading-mask')
        })
    }
}
const insertDom = (parent, el) => {
    if (!el.domVisible && el.style.display !== 'none' && el.style.visibility !== 'hidden') {
        if (el.originalPosition !== 'absolute' && el.originalPosition !== 'fixed') {
            el.style.position = 'relative'
        }

        el.domVisible = true

        // append to container
        parent.appendChild(el.mask)

        // show in next tick
        el.instance.visible = true

        // don't forget to unbind later
        el.domInserted = true
    }
}


export default {
    install(Vue, options) {
        let Mask = Vue.extend(Loading)

        Vue.directive('loading', {
            bind: function (el, binding, vnode) {

                const vm = vnode.context
                const textExr = el.getAttribute('loading-text')

                const mask = new Mask({
                    el: document.createElement('div'),
                    data: {
                        text: vm && (vm[textExr] || textExr),
                    }
                })

                el.instance = mask
                el.mask = mask.$el

                binding.value && toggleLoading(el, binding)

            },
            update: function (el, binding) {

                el.instance.setText(el.getAttribute('loading-text'))

                if (binding.oldValue !== binding.value) {
                    toggleLoading(el, binding)
                }
            },

            unbind: function (el) {
                if (el.domInserted) {
                    el.mask &&
                    el.mask.parentNode &&
                    el.mask.parentNode.removeChild(el.mask)

                    toggleLoading(el, {
                        value: false,
                        modifiers: {}
                    })
                }
                el.instance.$destroy()
            }
        })
    }
}
