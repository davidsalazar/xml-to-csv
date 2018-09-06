const {dialog} = require('electron').remote;
const fs = require('fs');
const {$} = require('./e-query');
const xpath = require('xpath');
const dom = require('xmldom').DOMParser

let browseBtn = $.find('#open-xml-file');
let saveBtn = $.find('#save-csv-file');


browseBtn.addEventListener('click' , () => {
    let files = dialog.showOpenDialog(
        {
            title : 'Import XML',
            filters : [
                {
                    name : 'XML File' , extensions : ['xml']
                }
            ],
            properties: ['openFile']
        }
    );    
    $.find('#open-path').value = files ? files[0] || '' : '';
})


let readXMLFile = (path) => {
    return new Promise((resolve,reject) => {
        fs.readFile(path,'utf8',(err,data) => {
            err ? reject(err) : resolve(data)
        })
    })
}

let getColumnSelectors = () => {
    return $.value('#col-xpath').trim().split('\n').map(v => v.trim()).filter(v => v !== "");
}

let getRowSelector = () => {
    return $.value('#row-xpath').trim();
}

let generateFlattenedCSV = ($row,doc) => {
    let csv = [];
    let rows = xpath.select($row,doc);
    if (!rows.length) return alert('Give row selector does not select any nodes.');
    for (let row of rows) {
        // flattenning selector selects all leaf nodes
        // console.log(row)
        // let cols = xpath.select('//text()',row);
        // console.log(cols)
        // let items = []
        // for (let col of cols) {
        //     items.push(col.replace(/\s+/g,' ').trim())
        // }
        // csv.push(
        //     items.map(
        //         v => v.includes(',') ? `"${v}"` : v
        //     ).join(',')
        // )
    }
    return '';
}

let generateSelectiveCSV = ($row,$col,doc) => {
    let csv = [];
    let rows = xpath.select($row,doc);
    console.log(rows);
    if (!rows.length) return alert('Give row selector does not select any nodes.');
    let header = $col.map(
        v => `${$row}/${v}`
    ).map(
        v => v.includes(',') ? `"${v}"` : v
    ).join(',');
    console.log(header);
    csv.push(header);

    for (let row of rows) {
        let items = []
        for (col of $col) {
            let item = xpath.select(`string(${col})`,row);
            items.push(item.replace(/\s+/g,' ').trim());
        }
        csv.push(
            items.map(
                v => v.includes(',') ? `"${v}"` : v
            ).join(',')
        )
    }
    // to make it work on windows
    return csv.join('\r\n');
}


let xml2csv = (str) => {
    str = str.replace(/xmlns=".+?"/,'');
    let doc = new dom().parseFromString(str);
    let $row = getRowSelector();
    console.log($row);
    if (!$row) return alert('Row selector sould not be left empty');
    let $col = getColumnSelectors();
    console.log($col);
    let csv = $col.length
        ? generateSelectiveCSV($row,$col,doc)
        : generateFlattenedCSV($row,doc);
    console.log(csv);
    return csv
}

writeToFile = (csv,path) => {
    return new Promise((resolve,reject) => {
        if (csv === '') return resolve(false);
        fs.writeFile(path,csv,'utf8',(err) => {
            err ?  reject(err) : resolve(true)
        })
    });
}

saveBtn.addEventListener('click' , () => {
    let savePath = dialog.showSaveDialog({
        title : 'Export CSV',
        filters: [
            {name: 'CSV File', extensions: ['csv']},
        ]
    });
    if (!savePath) return;
    let xmlPath = $.find('#open-path').value.trim();
    readXMLFile(xmlPath).then(data => {
        return xml2csv(data);
    }).then(csv => {
        return writeToFile(csv,savePath);
    }).then(res => {
        res ? alert('CSV was written to ' + savePath) : alert('Resulting CSV was empty. Therefore not written to disk');
    })
    .catch(e => {
        console.log(e);
    })
})