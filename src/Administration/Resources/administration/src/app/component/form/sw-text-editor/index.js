import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import { Component } from 'src/core/shopware';
import template from './sw-text-editor.html.twig';
import './sw-text-editor.less';

Component.register('sw-text-editor', {
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: ''
        },

        label: {
            type: String,
            required: false,
            default: ''
        },

        placeholder: {
            type: String,
            required: false,
            default: ''
        },

        htmlContent: {
            type: Boolean,
            required: false,
            default: true
        },

        toolbarConfig: {
            type: Array,
            required: false,
            default() {
                return [
                    [{ header: [1, 2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ align: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'blockquote', 'code-block'],
                    ['clean']
                ];
            }
        }
    },

    data() {
        return {
            textLength: 0
        };
    },

    watch: {
        value(value) {
            if (value !== this.editor.root.innerHTML) {
                this.setText(value);
            }
        }
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        mountedComponent() {
            this.editor = new Quill(this.$refs.editor, {
                theme: 'snow',
                placeholder: this.placeholder,
                modules: {
                    toolbar: this.toolbarConfig
                }
            });

            this.setText(this.value);

            this.editor.on('text-change', this.onTextChange);
        },

        destroyedComponent() {
            delete this.editor;
        },

        setText(value) {
            if (value !== null && value.length && value !== '<h2><br></h2>') {
                if (this.htmlContent) {
                    this.editor.clipboard.dangerouslyPasteHTML(value);
                } else {
                    this.editor.setText(value);
                }
            }
        },

        getText() {
            return this.editor.getText();
        },

        getHTML() {
            return this.editor.root.innerHTML;
        },

        onTextChange() {
            const htmlValue = this.getHTML();
            const textValue = this.getText();

            if (this.htmlContent) {
                this.$emit('input', (htmlValue === '<p><br></p>') ? '' : htmlValue);
            } else {
                this.$emit('input', textValue);
            }

            // The text of the quill editor always contains "\n".
            this.textLength = textValue.length - 1;
        }
    }
});
