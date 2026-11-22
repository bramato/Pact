import test from "node:test";
import assert from "node:assert/strict";
import {Pact} from "../sdk/typescript/dist/index.js";
import {createHash} from "node:crypto";
import {readFileSync} from "node:fs";
import {resolve} from "node:path";

test("form answers preserve the selected value and reject other choices before resuming", () => {
  const base = resolve("sdk/laravel/resources/contracts");
  const bytes = readFileSync(base + "/answer.json");
  const question = {
    question_id:"choose",task_id:"task",prompt:"Modello?",required:true,status:"open",expires_at:null,
    answer_schema:{schema_uri:JSON.parse(bytes).$id,sha256:createHash("sha256").update(bytes).digest("hex")},
    form:{version:1,fields:[{key:"model",label:"Modello",type:"string",required:true,options:[{value:"nuno",label:"Nuno Nogueira"}]}]},
  };
  const ctx = {taskId:"task",contextId:"ctx",expectedContextId:"ctx",registryPath:base+"/registry.json",now:new Date().toISOString()};
  const pact = new Pact();
  const answer = model => pact.answers([question],[{response_to:"choose",task_id:"task",value:{model}}],ctx);
  assert.throws(() => answer("other"));
  assert.equal(answer("nuno")[0].status,"answered");
  assert.equal(question.status,"open");
});
