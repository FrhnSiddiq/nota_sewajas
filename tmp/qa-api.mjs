import {run as probes} from './qa-probes.mjs';
export async function run(args){process.env.QA_API_ONLY='1';await probes(args)}
