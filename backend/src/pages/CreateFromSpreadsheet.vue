<template>
  <div @drop="_drop" @dragenter="_suppress" @dragover="_suppress">
    <div v-if="fileUploadDone" class="mb-4 bg-white rounded-lg p-6">
      <label class="block uppercase tracking-wide text-xs font-bold"
        >Name</label
      >
      <div class="flex justify-between">
        <input
          v-model="tableTitle"
          type="text"
          class="block w-full focus:outline-0 bg-white py-3 px-6 mr-2 mb-2 sm:mb-0"
          name="name"
          placeholder="Enter the Table Name"
          required=""
        />
        <div v-if="!isSaving" style="display: contents">
          <button
            v-if="!['/settings', '/edit', '/'].some((e) => e == $route.path)"
            @click="handleSave"
            class="rounded-lg bg-blue-500 hover:bg-blue-800 text-white py-2 px-4"
          >
            Save
          </button>
          <button
            v-if="$route.path == '/edit' && $route.query.table_id"
            @click="handleUpdate"
            class="rounded-lg bg-blue-500 hover:bg-blue-800 font-bold text-white py-2 px-4"
          >
            Update
          </button>
        </div>
        <div v-if="isSaving">
          <button
            class="rounded-lg bg-blue-500 hover:bg-blue-800 text-white py-2 px-4"
          >
            <i class="gg-spinner-alt"></i>
          </button>
        </div>
      </div>
    </div>
    <div
      class="w-3/6 mx-auto px-4 py-8 justify-center rounded-sm bg-white border-t-4 border-brand"
      v-if="!fileUploadDone"
    >
      <form>
        <div class="mb-4 bg-white rounded-lg p-6">
          <label class="block tracking-wide text-xs font-bold"
            >Upload a Spreadsheet (Upload Excel files, or CSV files)</label
          >
          <input
            type="file"
            class="w-full shadow-inner p-4 border-0"
            id="file"
            :accept="SheetJSFT"
            @change="_change"
          />
        </div>
      </form>
    </div>
    <table-editor v-if="$store.state.grid.data.length" />
    <settings-modal v-if="showSettings" />
    <feedback-modal v-if="showFeedback" />
  </div>
</template>
<style>
.main-nav li {
  margin-bottom: 0;
}
canvas-datagrid {
  overflow: scroll;
}
</style>
<script>
import * as XLSX from 'xlsx';
import CanvasDatagrid from 'canvas-datagrid';
import tablesaw from 'tablesaw';
import tableEditor from '../components/tableEditor.vue';
import settingsModal from '../components/settings-modal/index.vue';
import feedbackModal from '../components/FeedbackModal.vue';
import storeUtils from '../utils/storeUtils';
const make_cols = (refstr) =>
  Array(XLSX.utils.decode_range(refstr).e.c + 1)
    .fill(0)
    .map((x, i) => ({ name: XLSX.utils.encode_col(i), key: i }));
const _SheetJSFT = [
  'xlsx',
  'xlsb',
  'xlsm',
  'xls',
  'xml',
  'csv',
  'txt',
  'ods',
  'fods',
  'uos',
  'sylk',
  'dif',
  'dbf',
  'prn',
  'qpw',
  '123',
  'wb*',
  'wq*',
  'html',
  'htm',
]
  .map(function (x) {
    return '.' + x;
  })
  .join(',');
export default {
  data() {
    return {
      SheetJSFT: _SheetJSFT,
      isSaving: false,
      fileUploadDone: false,
    };
  },
  methods: {
    _suppress(evt) {
      evt.stopPropagation();
      evt.preventDefault();
    },
    _drop(evt) {
      evt.stopPropagation();
      evt.preventDefault();
      const files = evt.dataTransfer.files;
      if (files && files[0]) this._file(files[0]);
    },
    _change(evt) {
      const files = evt.target.files;
      if (files && files[0]) this._file(files[0]);
    },
    _export(evt) {
      /* convert state to workbook */
      const ws = XLSX.utils.aoa_to_sheet(this.data);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'SheetJS');
      /* generate file and send to client */
      XLSX.writeFile(wb, 'sheetjs.xlsx');
    },
    to_json(workbook) {
      if (workbook.SSF) XLSX.SSF.load_table(workbook.SSF);
      var result = {};
      workbook.SheetNames.forEach(function (sheetName) {
        var roa = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {
          raw: false,
          header: 1,
        });
        if (roa.length > 0) result[sheetName] = roa;
      });
      return result;
    },
    _file(file) {
      /* Boilerplate to set up FileReader */
      const reader = new FileReader();
      reader.onload = (e) => {
        /* Parse data */
        const bstr = e.target.result;
        const wb = XLSX.read(bstr, { type: 'binary' });
        /* Get first worksheet */
        const wsname = wb.SheetNames[0];

        var json = this.to_json(wb)[wsname];
        const ws = wb.Sheets[wsname];
        /* Convert array of arrays */
        const data = XLSX.utils.sheet_to_json(ws, {
          raw: true,
          cellDates: false,
        });
        /* Update state */
        // this.grid = {data: json};
        this.$store.commit('updateGrid', json);
        this.cols = make_cols(ws['!ref']);
      };
      reader.readAsBinaryString(file);
      let fileNameDotLength = file.name.split('.').length - 1;
      let possibleTitle = file.name.split('.', fileNameDotLength).join('_');
      this.$store.commit('setTitle', possibleTitle);
      this.fileUploadDone = true;
    },
    handleSave() {
      let data = storeUtils.read(this.$store.state);
      jQuery.ajax({
        type: 'POST',
        url:
          ajaxurl +
          '?action=sprdsh_create_new_table_entry' +
          '&nonce=' +
          this.$store.state.backendConfig.nonce,
        dataType: 'json',
        data: JSON.stringify(data),
        success: (responseData) => {
          this.$router.push({
            name: 'Edit Existing',
            query: { table_id: responseData.ok },
          });
        },
      });
    },
  },
  computed: {
    tableTitle: {
      get: function () {
        return this.$store.state.tableTitle;
      },
      set: function (newString) {
        return (this.$store.state.tableTitle = newString);
      },
    },
    showSettings: (vm) => {
      return vm.$store.state.showSettings;
    },
    showFeedback: (vm) => {
      return vm.$store.state.showFeedbackModal;
    },
  },
  components: {
    tableEditor,
    settingsModal,
    feedbackModal,
  },
  mounted() {
    // Tablesaw.init();
    this.$store.commit('setPageTitle', 'Table Editor');
  },
};
</script>
