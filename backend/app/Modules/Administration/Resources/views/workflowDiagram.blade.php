<!DOCTYPE html>
<html>
<head>
    <title></title>
    <script type="text/javascript" src="{{asset('resources/bower_components/gojs/release/go.js')}}"></script>
    <script id="code">
        // define a custom ForceDirectedLayout for this sample
        function DemoForceDirectedLayout() {
            go.ForceDirectedLayout.call(this);
        }

        go.Diagram.inherit(DemoForceDirectedLayout, go.ForceDirectedLayout);

        // Override the makeNetwork method to also initialize
        // ForceDirectedVertex.isFixed from the corresponding Node.isSelected.
        /** @override */
        DemoForceDirectedLayout.prototype.makeNetwork = function (coll) {
            // call base method for standard behavior
            var net = go.ForceDirectedLayout.prototype.makeNetwork.call(this, coll);
            net.vertexes.each(function (vertex) {
                var node = vertex.node;
                if (node !== null) vertex.isFixed = node.isSelected;
            });
            return net;
        };

        // This variation on ForceDirectedLayout does not move any selected Nodes
        // but does move all other nodes (vertexes).
        function ContinuousForceDirectedLayout() {
            go.ForceDirectedLayout.call(this);
            this._isObserving = false;
        }

        go.Diagram.inherit(ContinuousForceDirectedLayout, go.ForceDirectedLayout);

        /** @override */
        ContinuousForceDirectedLayout.prototype.isFixed = function (v) {
            return v.node.isSelected;
        };

        // optimization: reuse the ForceDirectedNetwork rather than re-create it each time
        /** @override */
        ContinuousForceDirectedLayout.prototype.doLayout = function (coll) {
            if (!this._isObserving) {
                this._isObserving = true;
                // cacheing the network means we need to recreate it if nodes or links have been added or removed or relinked,
                // so we need to track structural model changes to discard the saved network.
                var lay = this;
                this.diagram.addModelChangedListener(function (e) {
                    // modelChanges include a few cases that we don't actually care about, such as
                    // "nodeCategory" or "linkToPortId", but we'll go ahead and recreate the network anyway.
                    // Also clear the network when replacing the model.
                    if (e.modelChange !== "" ||
                        (e.change === go.ChangedEvent.Transaction && e.propertyName === "StartingFirstTransaction")) {
                        lay.network = null;
                    }
                });
            }
            var net = this.network;
            if (net === null) {  // the first time, just create the network as normal
                this.network = net = this.makeNetwork(coll);
            } else {  // but on reuse we need to update the LayoutVertex.bounds for selected nodes
                this.diagram.nodes.each(function (n) {
                    var v = net.findVertex(n);
                    if (v !== null) v.bounds = n.actualBounds;
                });
            }
            // now perform the normal layout
            go.ForceDirectedLayout.prototype.doLayout.call(this, coll);
            // doLayout normally discards the LayoutNetwork by setting Layout.network to null;
            // here we remember it for next time
            this.network = net;
        };
        // end ContinuousForceDirectedLayout

        // end DemoForceDirectedLayout class
        function init() {
            //if (window.goSamples) goSamples();  // init for these samples -- you don't need to call this
            var $ = go.GraphObject.make;  // for conciseness in defining templates
            myDiagram =
                $(go.Diagram, "myDiagramDiv",  // must name or refer to the DIV HTML element
                    {
                        //layout: $(go.GridLayout, { wrappingColumn: 5, spacing: new go.Size(10,10) }),
                        /* layout: $(go.GridLayout,
                             { comparer: go.GridLayout.smartComparer }),*/
                        //layout: new DemoForceDirectedLayout(),
                        /*    initialDocumentSpot: go.Spot.TopCenter,
                            initialViewportSpot: go.Spot.TopCenter,
                            initialAutoScale: go.Diagram.Uniform,
                            // start everything in the middle of the viewport
                            initialContentAlignment: go.Spot.Center,*/

                        initialAutoScale: go.Diagram.None,//.Uniform,.UniformToFill  // an initial automatic zoom-to-fit
                        contentAlignment: go.Spot.Center,  // align document to the center of the viewport
                        layout:
                            $(ContinuousForceDirectedLayout,  // automatically spread nodes apart while dragging
                                {defaultSpringLength: 30, defaultElectricalCharge: 100}),
                        // do an extra layout at the end of a move
                        "SelectionMoved": function (e) {
                            e.diagram.layout.invalidateLayout();
                        },
                        "grid.visible": true,
                        //"grid.gridCellSize": new go.Size(20, 20),
                        // have mouse wheel events zoom in and out instead of scroll up and down
                        "toolManager.mouseWheelBehavior": go.ToolManager.WheelZoom,
                        // support double-click in background creating a new node
                        //"clickCreatingTool.archetypeNodeData": {text: "new node"},
                        // enable undo & redo
                        "undoManager.isEnabled": true
                    });
            // dragging a node invalidates the Diagram.layout, causing a layout during the drag
            myDiagram.toolManager.draggingTool.doMouseMove = function () {
                go.DraggingTool.prototype.doMouseMove.call(this);
                if (this.isActive) {
                    this.diagram.layout.invalidateLayout();
                }
            }
            // when the document is modified, add a "*" to the title and enable the "Save" button
            myDiagram.addDiagramListener("Modified", function (e) {
                var button = document.getElementById("SaveButton");
                if (button) button.disabled = !myDiagram.isModified;
                var idx = document.title.indexOf("*");
                if (myDiagram.isModified) {
                    if (idx < 0) document.title += "*";
                } else {
                    if (idx >= 0) document.title = document.title.substr(0, idx);
                }
            });
            // define the Node template
            myDiagram.nodeTemplate =
                $(go.Node, "Auto",
                    new go.Binding("location", "loc", go.Point.parse).makeTwoWay(go.Point.stringify),
                    // define the node's outer shape, which will surround the TextBlock
                    $(go.Shape, "RoundedRectangle",
                        {
                            parameter1: 20,  // the corner has a large radius
                            fill: $(go.Brush, "Linear", {0: "rgb(254, 201, 0)", 1: "rgb(254, 162, 0)"}),
                            stroke: null,
                            portId: "",  // this Shape is the Node's port, not the whole Node
                            fromLinkable: false, fromLinkableSelfNode: false, fromLinkableDuplicates: false,
                            toLinkable: false, toLinkableSelfNode: false, toLinkableDuplicates: false,
                            cursor: "pointer"
                        }, new go.Binding("fill", "status", function (type) {
                            switch (type) {
                                case 1:
                                    return " #febd00";
                                default:
                                    return '#80ced6';

                            }
                        })),
                    $(go.TextBlock,
                        {
                            //stroke: "green",
                            font: "bold 12pt helvetica, bold arial, sans-serif",
                            editable: false  // editing the text automatically updates the model data
                        },
                        new go.Binding("text").makeTwoWay())
                );
            // unlike the normal selection Adornment, this one includes a Button
            myDiagram.nodeTemplate.selectionAdornmentTemplate =
                $(go.Adornment, "Spot",
                    $(go.Panel, "Auto",
                        $(go.Shape, {fill: null, stroke: "blue", strokeWidth: 2}),
                        $(go.Placeholder)  // a Placeholder sizes itself to the selected Node
                    ),
                    // the button to create a "next" node, at the top-right corner
                    /* $("Button",//start comment out kip
                         {
                             alignment: go.Spot.TopRight,
                             click: addNodeAndLink  // this function is defined below
                         },
                         $(go.Shape, "PlusLine", {width: 6, height: 6})
                     )*/ // end button//end comment out kip
                ); // end Adornment
            // clicking the button inserts a new node to the right of the selected node,
            // and adds a link to that new node
            function addNodeAndLink(e, obj) {
                var adornment = obj.part;
                var diagram = e.diagram;
                diagram.startTransaction("Add State");
                // get the node data for which the user clicked the button
                var fromNode = adornment.adornedPart;
                var fromData = fromNode.data;
                // create a new "State" data object, positioned off to the right of the adorned Node
                var toData = {text: "new"};
                var p = fromNode.location.copy();
                p.x += 200;
                toData.loc = go.Point.stringify(p);  // the "loc" property is a string, not a Point object
                // add the new node data to the model
                var model = diagram.model;
                model.addNodeData(toData);
                // create a link data from the old node data to the new node data
                var linkdata = {
                    from: model.getKeyForNodeData(fromData),  // or just: fromData.id
                    to: model.getKeyForNodeData(toData),
                    text: "transition"
                };
                // and add the link data to the model
                model.addLinkData(linkdata);
                // select the new Node
                var newnode = diagram.findNodeForData(toData);
                diagram.select(newnode);
                diagram.commitTransaction("Add State");
                // if the new node is off-screen, scroll the diagram to show the new node
                diagram.scrollToRect(newnode.actualBounds);
            }

            // replace the default Link template in the linkTemplateMap
            myDiagram.linkTemplate =
                $(go.Link,  // the whole link panel
                    {
                        curve: go.Link.Bezier, adjusting: go.Link.Stretch,
                        reshapable: true, relinkableFrom: false, relinkableTo: false,
                        toShortLength: 3
                    },
                    new go.Binding("points").makeTwoWay(),
                    new go.Binding("curviness"),
                    $(go.Shape,  // the link shape
                        {strokeWidth: 1.5}),
                    $(go.Shape,  // the arrowhead
                        {toArrow: "standard", stroke: null}),
                    $(go.Panel, "Auto",
                        $(go.Shape,  // the label background, which becomes transparent around the edges
                            {
                                fill: $(go.Brush, "Radial",
                                    {
                                        0: "rgb(240, 240, 240)",
                                        0.3: "rgb(240, 240, 240)",
                                        1: "rgba(240, 240, 240, 0)"
                                    }),
                                stroke: null
                            }),
                        $(go.TextBlock, "transition",  // the label text
                            {
                                stroke: "#004d00",
                                textAlign: "center",
                                font: "11pt helvetica, arial, sans-serif",
                                margin: 4,
                                editable: false  // enable in-place editing
                            },
                            // editing the text automatically updates the model data
                            new go.Binding("text").makeTwoWay())
                    )
                );
            // read in the JSON data from the "mySavedModel" element
            load();
        }

        // Show the diagram's model in JSON format
        function save() {
            document.getElementById("mySavedModel").value = myDiagram.model.toJson();
        }

        function load() {
            myDiagram.model = go.Model.fromJson(document.getElementById("mySavedModel").value);
        }
    </script>
    <style>
        .diagramDiv {
            /* position: absolute;
             top: 0;
             bottom: 0;
             left: 0;*/
            border: solid 1px #737373;
            width: 100%;
            height: 98vh;
        }
    </style>
</head>
<body onload="init()" style="margin:2px;">
<div id="myDiagramDiv" class="diagramDiv"></div>
<textarea hidden id="mySavedModel">
    <?php echo json_encode($workflowData);?>
</textarea>
</body>
</html>