console.log("Hi");

let owner = document.getElementById('owner_id');
let wallet = document.getElementById('wallet_id');
let host = window.location.origin;
let pages = document.getElementById('pages');
let cards = document.getElementById('cards');

let cvv = document.getElementById('cvv');
let card_number = document.getElementById('card_number');
let download = document.getElementById('download');

download.onclick = ((ev) => {
    ev.preventDefault();
});

let can = true;

let syncPages = (event) => {
    fetch(host + "/count_pages.php?wallet_id=" + wallet.value)
        .then(response => (response.text()).then((data) => {
            can = (data !== '0');
            pages.setAttribute('max', data);
        }))
        .catch(error => console.log(error));
};

let syncWallets = (event) => {
    fetch(host + "/list-wallets.php?owner_id=" + owner.value)
        .then(response => (response.text()).then((data) => {
            (wallet.innerHTML = data);
            syncPages();
        }))
        .catch(error => console.log(error));
};


owner.addEventListener('change', syncWallets);
wallet.addEventListener('change', syncPages);
syncWallets();

let fetchData = (event, type) => {
    event.preventDefault();
    cards.innerHTML = "";
    if (!can) {
        alert('No cards available!');
    } else {

        fetch(host + "/list-cards.php?wallet_id=" + wallet.value + "&page_number=" + pages.value + "&" + type + "=true")
            .then(response => (response.text()).then((data) => {
                let arr = JSON.parse(data);
                // console.log(data);
                GenerateTable(arr);
            }))
            .catch(error => console.log(error));

    }
};

cvv.addEventListener('click', (event) => {
    fetchData(event, 'cvv');
});

card_number.addEventListener('click', (event) => {
    fetchData(event, 'card_number');
});

function GenerateTable(customers) {
    //Create a HTML Table element.
    let table = document.createElement("TABLE");
    table.border = "1";

    for (let i = 0; i < customers.length; i++) {
        customers[i] = Object.values(customers[i]);
    }
    exportCSVFile(false, customers, 'somefile');

    //Get the count of columns.
    let columnCount = customers[0].length;

    //Add the data rows.
    for (let i = 0; i < customers.length; i++) {
        let row = table.insertRow(-1);
        for (let j = 0; j < columnCount; j++) {
            let cell = row.insertCell(-1);
            cell.innerHTML = customers[i][j];
        }
    }

    cards.innerHTML = "";
    cards.appendChild(table);
}

function convertToCSV(objArray) {
    let array = typeof objArray != 'object' ? JSON.parse(objArray) : objArray;
    let str = '';

    for (let i = 0; i < array.length; i++) {
        let line = '';
        for (let index in array[i]) {
            if (line != '') line += ','

            line += array[i][index];
        }

        str += line + '\r\n';
    }

    return str;
}

function exportCSVFile(headers, items, fileTitle) {
    if (headers) {
        items.unshift(headers);
    }

    // Convert Object to JSON
    let jsonObject = JSON.stringify(items);

    let csv = this.convertToCSV(jsonObject);

    let exportedFilenmae = fileTitle + '.csv' || 'export.csv';

    let blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilenmae);
    } else {
        let link = document.createElement("a");
        if (link.download !== undefined) { // feature detection
            // Browsers that support HTML5 download attribute
            let url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilenmae);
            // link.style.visibility = 'hidden';
            document.body.appendChild(link);
            download.onclick = ((ev) => {
                ev.preventDefault();
                link.click();
                document.body.removeChild(link);
            });
        }
    }
}
