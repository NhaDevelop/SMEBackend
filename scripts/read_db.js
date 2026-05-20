import fs from 'fs'

const data = JSON.parse(fs.readFileSync('/Users/paypanha/SMEFrontend/.data/sme_frontend_db.json', 'utf-8'));
console.log("Assessments samples:", JSON.stringify(data.assessments.slice(0, 3), null, 2));
