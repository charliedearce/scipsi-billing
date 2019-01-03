<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmbilltrans
    Inherits DevExpress.XtraEditors.XtraForm

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        If disposing AndAlso components IsNot Nothing Then
            components.Dispose()
        End If
        MyBase.Dispose(disposing)
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Me.GroupBox2 = New System.Windows.Forms.GroupBox()
        Me.dgbill = New System.Windows.Forms.DataGridView()
        Me.Column2 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column1 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column3 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column4 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column5 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column6 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column7 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.GroupBox1 = New System.Windows.Forms.GroupBox()
        Me.chkper = New DevExpress.XtraEditors.CheckEdit()
        Me.Label16 = New System.Windows.Forms.Label()
        Me.txtperiod = New System.Windows.Forms.TextBox()
        Me.GroupBox3 = New System.Windows.Forms.GroupBox()
        Me.btn2 = New DevExpress.XtraEditors.SimpleButton()
        Me.btn1 = New DevExpress.XtraEditors.SimpleButton()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.cbstatus = New System.Windows.Forms.ComboBox()
        Me.cbprint = New System.Windows.Forms.ComboBox()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.txtaccname = New System.Windows.Forms.TextBox()
        Me.Label11 = New System.Windows.Forms.Label()
        Me.Label8 = New System.Windows.Forms.Label()
        Me.Label7 = New System.Windows.Forms.Label()
        Me.Label6 = New System.Windows.Forms.Label()
        Me.Label4 = New System.Windows.Forms.Label()
        Me.lbldatetrans = New DevExpress.XtraEditors.DateEdit()
        Me.txtParticulars = New System.Windows.Forms.TextBox()
        Me.Label10 = New System.Windows.Forms.Label()
        Me.txtVessel = New System.Windows.Forms.TextBox()
        Me.cbType = New System.Windows.Forms.ComboBox()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.txtVoyage = New System.Windows.Forms.MaskedTextBox()
        Me.txtAccountno = New System.Windows.Forms.MaskedTextBox()
        Me.cbRoute = New System.Windows.Forms.ComboBox()
        Me.LabelControl5 = New DevExpress.XtraEditors.LabelControl()
        Me.txtbillnum = New System.Windows.Forms.TextBox()
        Me.GroupBox4 = New System.Windows.Forms.GroupBox()
        Me.lblvat = New System.Windows.Forms.Label()
        Me.lblscipsi = New System.Windows.Forms.Label()
        Me.lblppa = New System.Windows.Forms.Label()
        Me.lbltotal = New System.Windows.Forms.Label()
        Me.Label14 = New System.Windows.Forms.Label()
        Me.Label15 = New System.Windows.Forms.Label()
        Me.Label9 = New System.Windows.Forms.Label()
        Me.Label5 = New System.Windows.Forms.Label()
        Me.lblnet = New System.Windows.Forms.Label()
        Me.lbldisc = New System.Windows.Forms.Label()
        Me.Label12 = New System.Windows.Forms.Label()
        Me.Label13 = New System.Windows.Forms.Label()
        Me.GroupBox5 = New System.Windows.Forms.GroupBox()
        Me.lblemp = New System.Windows.Forms.Label()
        Me.GroupBox6 = New System.Windows.Forms.GroupBox()
        Me.SimpleButton2 = New DevExpress.XtraEditors.SimpleButton()
        Me.SimpleButton1 = New DevExpress.XtraEditors.SimpleButton()
        Me.DataGridView1 = New System.Windows.Forms.DataGridView()
        Me.aa = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.DataGridViewTextBoxColumn1 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.DataGridViewTextBoxColumn2 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.DataGridViewTextBoxColumn5 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.DataGridViewTextBoxColumn4 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.DataGridViewTextBoxColumn3 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.GroupBox2.SuspendLayout()
        CType(Me.dgbill, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox1.SuspendLayout()
        CType(Me.chkper.Properties, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox3.SuspendLayout()
        CType(Me.lbldatetrans.Properties.CalendarTimeProperties, System.ComponentModel.ISupportInitialize).BeginInit()
        CType(Me.lbldatetrans.Properties, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox4.SuspendLayout()
        Me.GroupBox5.SuspendLayout()
        Me.GroupBox6.SuspendLayout()
        CType(Me.DataGridView1, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.SuspendLayout()
        '
        'GroupBox2
        '
        Me.GroupBox2.Controls.Add(Me.dgbill)
        Me.GroupBox2.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox2.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox2.Location = New System.Drawing.Point(15, 370)
        Me.GroupBox2.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox2.Name = "GroupBox2"
        Me.GroupBox2.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox2.Size = New System.Drawing.Size(1099, 214)
        Me.GroupBox2.TabIndex = 27
        Me.GroupBox2.TabStop = False
        Me.GroupBox2.Text = "BILLING ITEMS"
        '
        'dgbill
        '
        Me.dgbill.AllowUserToResizeRows = False
        Me.dgbill.BackgroundColor = System.Drawing.Color.White
        Me.dgbill.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.dgbill.CellBorderStyle = System.Windows.Forms.DataGridViewCellBorderStyle.None
        Me.dgbill.ColumnHeadersHeightSizeMode = System.Windows.Forms.DataGridViewColumnHeadersHeightSizeMode.AutoSize
        Me.dgbill.Columns.AddRange(New System.Windows.Forms.DataGridViewColumn() {Me.Column2, Me.Column1, Me.Column3, Me.Column4, Me.Column5, Me.Column6, Me.Column7})
        Me.dgbill.Location = New System.Drawing.Point(10, 25)
        Me.dgbill.MultiSelect = False
        Me.dgbill.Name = "dgbill"
        Me.dgbill.ReadOnly = True
        Me.dgbill.RowHeadersVisible = False
        Me.dgbill.RowTemplate.Height = 24
        Me.dgbill.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.FullRowSelect
        Me.dgbill.Size = New System.Drawing.Size(1080, 173)
        Me.dgbill.TabIndex = 0
        '
        'Column2
        '
        Me.Column2.DataPropertyName = "it_unit"
        Me.Column2.HeaderText = "UNIT"
        Me.Column2.Name = "Column2"
        Me.Column2.ReadOnly = True
        '
        'Column1
        '
        Me.Column1.DataPropertyName = "it_qty"
        Me.Column1.HeaderText = "QTY"
        Me.Column1.Name = "Column1"
        Me.Column1.ReadOnly = True
        '
        'Column3
        '
        Me.Column3.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.Column3.DataPropertyName = "it_service"
        Me.Column3.HeaderText = "SERVICE"
        Me.Column3.Name = "Column3"
        Me.Column3.ReadOnly = True
        '
        'Column4
        '
        Me.Column4.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.Column4.DataPropertyName = "it_cargo"
        Me.Column4.HeaderText = "CARGO"
        Me.Column4.Name = "Column4"
        Me.Column4.ReadOnly = True
        '
        'Column5
        '
        Me.Column5.DataPropertyName = "it_rate"
        Me.Column5.HeaderText = "RATE"
        Me.Column5.Name = "Column5"
        Me.Column5.ReadOnly = True
        '
        'Column6
        '
        Me.Column6.DataPropertyName = "it_disc"
        Me.Column6.HeaderText = "DISC."
        Me.Column6.Name = "Column6"
        Me.Column6.ReadOnly = True
        '
        'Column7
        '
        Me.Column7.DataPropertyName = "it_gross"
        Me.Column7.HeaderText = "GROSS"
        Me.Column7.Name = "Column7"
        Me.Column7.ReadOnly = True
        '
        'GroupBox1
        '
        Me.GroupBox1.Controls.Add(Me.chkper)
        Me.GroupBox1.Controls.Add(Me.Label16)
        Me.GroupBox1.Controls.Add(Me.txtperiod)
        Me.GroupBox1.Controls.Add(Me.GroupBox3)
        Me.GroupBox1.Controls.Add(Me.Label3)
        Me.GroupBox1.Controls.Add(Me.cbstatus)
        Me.GroupBox1.Controls.Add(Me.cbprint)
        Me.GroupBox1.Controls.Add(Me.Label1)
        Me.GroupBox1.Controls.Add(Me.txtaccname)
        Me.GroupBox1.Controls.Add(Me.Label11)
        Me.GroupBox1.Controls.Add(Me.Label8)
        Me.GroupBox1.Controls.Add(Me.Label7)
        Me.GroupBox1.Controls.Add(Me.Label6)
        Me.GroupBox1.Controls.Add(Me.Label4)
        Me.GroupBox1.Controls.Add(Me.lbldatetrans)
        Me.GroupBox1.Controls.Add(Me.txtParticulars)
        Me.GroupBox1.Controls.Add(Me.Label10)
        Me.GroupBox1.Controls.Add(Me.txtVessel)
        Me.GroupBox1.Controls.Add(Me.cbType)
        Me.GroupBox1.Controls.Add(Me.Label2)
        Me.GroupBox1.Controls.Add(Me.txtVoyage)
        Me.GroupBox1.Controls.Add(Me.txtAccountno)
        Me.GroupBox1.Controls.Add(Me.cbRoute)
        Me.GroupBox1.Controls.Add(Me.LabelControl5)
        Me.GroupBox1.Controls.Add(Me.txtbillnum)
        Me.GroupBox1.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.Location = New System.Drawing.Point(15, 16)
        Me.GroupBox1.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox1.Size = New System.Drawing.Size(688, 348)
        Me.GroupBox1.TabIndex = 26
        Me.GroupBox1.TabStop = False
        Me.GroupBox1.Text = "BILLING MANAGEMENT"
        '
        'chkper
        '
        Me.chkper.Location = New System.Drawing.Point(525, 189)
        Me.chkper.Name = "chkper"
        Me.chkper.Properties.Appearance.Font = New System.Drawing.Font("Segoe UI", 10.2!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.chkper.Properties.Appearance.Options.UseFont = True
        Me.chkper.Properties.Caption = "EDIT PERIOD"
        Me.chkper.Size = New System.Drawing.Size(133, 27)
        Me.chkper.TabIndex = 130
        '
        'Label16
        '
        Me.Label16.AutoSize = True
        Me.Label16.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label16.ForeColor = System.Drawing.Color.Black
        Me.Label16.Location = New System.Drawing.Point(520, 160)
        Me.Label16.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label16.Name = "Label16"
        Me.Label16.Size = New System.Drawing.Size(83, 28)
        Me.Label16.TabIndex = 129
        Me.Label16.Text = "PERIOD:"
        '
        'txtperiod
        '
        Me.txtperiod.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtperiod.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtperiod.Location = New System.Drawing.Point(526, 218)
        Me.txtperiod.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtperiod.MaxLength = 50
        Me.txtperiod.Name = "txtperiod"
        Me.txtperiod.ReadOnly = True
        Me.txtperiod.Size = New System.Drawing.Size(119, 34)
        Me.txtperiod.TabIndex = 128
        '
        'GroupBox3
        '
        Me.GroupBox3.Controls.Add(Me.btn2)
        Me.GroupBox3.Controls.Add(Me.btn1)
        Me.GroupBox3.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox3.Font = New System.Drawing.Font("Segoe UI", 10.2!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox3.Location = New System.Drawing.Point(367, 252)
        Me.GroupBox3.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox3.Name = "GroupBox3"
        Me.GroupBox3.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox3.Size = New System.Drawing.Size(302, 78)
        Me.GroupBox3.TabIndex = 28
        Me.GroupBox3.TabStop = False
        Me.GroupBox3.Text = "FUNCTIONS"
        '
        'btn2
        '
        Me.btn2.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn2.Appearance.Options.UseFont = True
        Me.btn2.Location = New System.Drawing.Point(9, 30)
        Me.btn2.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn2.Name = "btn2"
        Me.btn2.Size = New System.Drawing.Size(132, 34)
        Me.btn2.TabIndex = 21
        Me.btn2.Text = "Delete"
        Me.btn2.Visible = False
        '
        'btn1
        '
        Me.btn1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn1.Appearance.Options.UseFont = True
        Me.btn1.Location = New System.Drawing.Point(159, 30)
        Me.btn1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn1.Name = "btn1"
        Me.btn1.Size = New System.Drawing.Size(132, 34)
        Me.btn1.TabIndex = 19
        Me.btn1.Text = "Update"
        Me.btn1.Visible = False
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.ForeColor = System.Drawing.Color.Black
        Me.Label3.Location = New System.Drawing.Point(521, 90)
        Me.Label3.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(85, 28)
        Me.Label3.TabIndex = 127
        Me.Label3.Text = "STATUS:"
        '
        'cbstatus
        '
        Me.cbstatus.BackColor = System.Drawing.Color.White
        Me.cbstatus.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbstatus.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbstatus.ForeColor = System.Drawing.Color.Black
        Me.cbstatus.Items.AddRange(New Object() {"TRUE", "FALSE"})
        Me.cbstatus.Location = New System.Drawing.Point(525, 123)
        Me.cbstatus.Margin = New System.Windows.Forms.Padding(4)
        Me.cbstatus.Name = "cbstatus"
        Me.cbstatus.Size = New System.Drawing.Size(120, 36)
        Me.cbstatus.TabIndex = 126
        '
        'cbprint
        '
        Me.cbprint.BackColor = System.Drawing.Color.White
        Me.cbprint.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbprint.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbprint.ForeColor = System.Drawing.Color.Black
        Me.cbprint.Items.AddRange(New Object() {"Y", "N"})
        Me.cbprint.Location = New System.Drawing.Point(525, 52)
        Me.cbprint.Margin = New System.Windows.Forms.Padding(4)
        Me.cbprint.Name = "cbprint"
        Me.cbprint.Size = New System.Drawing.Size(64, 36)
        Me.cbprint.TabIndex = 124
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(521, 24)
        Me.Label1.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(93, 28)
        Me.Label1.TabIndex = 125
        Me.Label1.Text = "PRINTED:"
        '
        'txtaccname
        '
        Me.txtaccname.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtaccname.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtaccname.BackColor = System.Drawing.Color.White
        Me.txtaccname.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtaccname.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtaccname.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtaccname.ForeColor = System.Drawing.Color.Black
        Me.txtaccname.Location = New System.Drawing.Point(17, 127)
        Me.txtaccname.Margin = New System.Windows.Forms.Padding(4)
        Me.txtaccname.MaxLength = 50
        Me.txtaccname.Name = "txtaccname"
        Me.txtaccname.Size = New System.Drawing.Size(316, 34)
        Me.txtaccname.TabIndex = 123
        '
        'Label11
        '
        Me.Label11.AutoSize = True
        Me.Label11.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label11.ForeColor = System.Drawing.Color.Black
        Me.Label11.Location = New System.Drawing.Point(13, 252)
        Me.Label11.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label11.Name = "Label11"
        Me.Label11.Size = New System.Drawing.Size(135, 28)
        Me.Label11.TabIndex = 122
        Me.Label11.Text = "PARTICULARs:"
        '
        'Label8
        '
        Me.Label8.AutoSize = True
        Me.Label8.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label8.ForeColor = System.Drawing.Color.Black
        Me.Label8.Location = New System.Drawing.Point(361, 25)
        Me.Label8.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label8.Name = "Label8"
        Me.Label8.Size = New System.Drawing.Size(77, 28)
        Me.Label8.TabIndex = 121
        Me.Label8.Text = "ROUTE:"
        '
        'Label7
        '
        Me.Label7.AutoSize = True
        Me.Label7.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label7.ForeColor = System.Drawing.Color.Black
        Me.Label7.Location = New System.Drawing.Point(13, 225)
        Me.Label7.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label7.Name = "Label7"
        Me.Label7.Size = New System.Drawing.Size(108, 28)
        Me.Label7.TabIndex = 120
        Me.Label7.Text = "VOYAGE #:"
        '
        'Label6
        '
        Me.Label6.AutoSize = True
        Me.Label6.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label6.ForeColor = System.Drawing.Color.Black
        Me.Label6.Location = New System.Drawing.Point(13, 160)
        Me.Label6.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label6.Name = "Label6"
        Me.Label6.Size = New System.Drawing.Size(79, 28)
        Me.Label6.TabIndex = 119
        Me.Label6.Text = "VESSEL:"
        '
        'Label4
        '
        Me.Label4.AutoSize = True
        Me.Label4.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label4.ForeColor = System.Drawing.Color.Black
        Me.Label4.Location = New System.Drawing.Point(13, 95)
        Me.Label4.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label4.Name = "Label4"
        Me.Label4.Size = New System.Drawing.Size(124, 28)
        Me.Label4.TabIndex = 118
        Me.Label4.Text = "ACCOUNT #:"
        '
        'lbldatetrans
        '
        Me.lbldatetrans.EditValue = Nothing
        Me.lbldatetrans.Location = New System.Drawing.Point(367, 188)
        Me.lbldatetrans.Margin = New System.Windows.Forms.Padding(4)
        Me.lbldatetrans.Name = "lbldatetrans"
        Me.lbldatetrans.Properties.Appearance.BackColor = System.Drawing.Color.Transparent
        Me.lbldatetrans.Properties.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbldatetrans.Properties.Appearance.ForeColor = System.Drawing.Color.Maroon
        Me.lbldatetrans.Properties.Appearance.Options.UseBackColor = True
        Me.lbldatetrans.Properties.Appearance.Options.UseFont = True
        Me.lbldatetrans.Properties.Appearance.Options.UseForeColor = True
        Me.lbldatetrans.Properties.BorderStyle = DevExpress.XtraEditors.Controls.BorderStyles.Simple
        Me.lbldatetrans.Properties.Buttons.AddRange(New DevExpress.XtraEditors.Controls.EditorButton() {New DevExpress.XtraEditors.Controls.EditorButton(DevExpress.XtraEditors.Controls.ButtonPredefines.Combo)})
        Me.lbldatetrans.Properties.CalendarTimeProperties.Buttons.AddRange(New DevExpress.XtraEditors.Controls.EditorButton() {New DevExpress.XtraEditors.Controls.EditorButton(DevExpress.XtraEditors.Controls.ButtonPredefines.Combo)})
        Me.lbldatetrans.RightToLeft = System.Windows.Forms.RightToLeft.Yes
        Me.lbldatetrans.Size = New System.Drawing.Size(133, 34)
        Me.lbldatetrans.TabIndex = 117
        '
        'txtParticulars
        '
        Me.txtParticulars.BackColor = System.Drawing.Color.White
        Me.txtParticulars.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtParticulars.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtParticulars.ForeColor = System.Drawing.Color.Black
        Me.txtParticulars.Location = New System.Drawing.Point(17, 281)
        Me.txtParticulars.Margin = New System.Windows.Forms.Padding(4)
        Me.txtParticulars.MaxLength = 50
        Me.txtParticulars.Name = "txtParticulars"
        Me.txtParticulars.Size = New System.Drawing.Size(316, 34)
        Me.txtParticulars.TabIndex = 116
        '
        'Label10
        '
        Me.Label10.AutoSize = True
        Me.Label10.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label10.ForeColor = System.Drawing.Color.Black
        Me.Label10.Location = New System.Drawing.Point(361, 160)
        Me.Label10.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label10.Name = "Label10"
        Me.Label10.Size = New System.Drawing.Size(159, 28)
        Me.Label10.TabIndex = 115
        Me.Label10.Text = "DATE OF TRANS:"
        '
        'txtVessel
        '
        Me.txtVessel.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtVessel.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtVessel.BackColor = System.Drawing.Color.White
        Me.txtVessel.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtVessel.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtVessel.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtVessel.ForeColor = System.Drawing.Color.Black
        Me.txtVessel.Location = New System.Drawing.Point(17, 188)
        Me.txtVessel.Margin = New System.Windows.Forms.Padding(4)
        Me.txtVessel.MaxLength = 50
        Me.txtVessel.Name = "txtVessel"
        Me.txtVessel.Size = New System.Drawing.Size(316, 34)
        Me.txtVessel.TabIndex = 105
        '
        'cbType
        '
        Me.cbType.BackColor = System.Drawing.Color.White
        Me.cbType.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbType.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbType.ForeColor = System.Drawing.Color.Black
        Me.cbType.Items.AddRange(New Object() {"I", "O"})
        Me.cbType.Location = New System.Drawing.Point(367, 124)
        Me.cbType.Margin = New System.Windows.Forms.Padding(4)
        Me.cbType.Name = "cbType"
        Me.cbType.Size = New System.Drawing.Size(64, 36)
        Me.cbType.TabIndex = 108
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(361, 95)
        Me.Label2.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(58, 28)
        Me.Label2.TabIndex = 114
        Me.Label2.Text = "TYPE:"
        '
        'txtVoyage
        '
        Me.txtVoyage.BackColor = System.Drawing.Color.White
        Me.txtVoyage.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtVoyage.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtVoyage.ForeColor = System.Drawing.Color.Black
        Me.txtVoyage.Location = New System.Drawing.Point(127, 225)
        Me.txtVoyage.Margin = New System.Windows.Forms.Padding(4)
        Me.txtVoyage.Mask = "##########"
        Me.txtVoyage.Name = "txtVoyage"
        Me.txtVoyage.Size = New System.Drawing.Size(95, 27)
        Me.txtVoyage.TabIndex = 106
        Me.txtVoyage.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'txtAccountno
        '
        Me.txtAccountno.BackColor = System.Drawing.Color.White
        Me.txtAccountno.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtAccountno.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtAccountno.ForeColor = System.Drawing.Color.Black
        Me.txtAccountno.Location = New System.Drawing.Point(136, 95)
        Me.txtAccountno.Margin = New System.Windows.Forms.Padding(4)
        Me.txtAccountno.Mask = "#####-####"
        Me.txtAccountno.Name = "txtAccountno"
        Me.txtAccountno.Size = New System.Drawing.Size(136, 27)
        Me.txtAccountno.TabIndex = 104
        Me.txtAccountno.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'cbRoute
        '
        Me.cbRoute.BackColor = System.Drawing.Color.White
        Me.cbRoute.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbRoute.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbRoute.ForeColor = System.Drawing.Color.Black
        Me.cbRoute.Items.AddRange(New Object() {"D", "F"})
        Me.cbRoute.Location = New System.Drawing.Point(367, 57)
        Me.cbRoute.Margin = New System.Windows.Forms.Padding(4)
        Me.cbRoute.Name = "cbRoute"
        Me.cbRoute.Size = New System.Drawing.Size(64, 36)
        Me.cbRoute.TabIndex = 107
        '
        'LabelControl5
        '
        Me.LabelControl5.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl5.Location = New System.Drawing.Point(17, 25)
        Me.LabelControl5.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.LabelControl5.Name = "LabelControl5"
        Me.LabelControl5.Size = New System.Drawing.Size(200, 28)
        Me.LabelControl5.TabIndex = 15
        Me.LabelControl5.Text = "SEARCH BILL NUMBER:"
        '
        'txtbillnum
        '
        Me.txtbillnum.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtbillnum.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtbillnum.Location = New System.Drawing.Point(17, 59)
        Me.txtbillnum.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtbillnum.MaxLength = 50
        Me.txtbillnum.Name = "txtbillnum"
        Me.txtbillnum.Size = New System.Drawing.Size(199, 34)
        Me.txtbillnum.TabIndex = 1
        '
        'GroupBox4
        '
        Me.GroupBox4.Controls.Add(Me.lblvat)
        Me.GroupBox4.Controls.Add(Me.lblscipsi)
        Me.GroupBox4.Controls.Add(Me.lblppa)
        Me.GroupBox4.Controls.Add(Me.lbltotal)
        Me.GroupBox4.Controls.Add(Me.Label14)
        Me.GroupBox4.Controls.Add(Me.Label15)
        Me.GroupBox4.Controls.Add(Me.Label9)
        Me.GroupBox4.Controls.Add(Me.Label5)
        Me.GroupBox4.Controls.Add(Me.lblnet)
        Me.GroupBox4.Controls.Add(Me.lbldisc)
        Me.GroupBox4.Controls.Add(Me.Label12)
        Me.GroupBox4.Controls.Add(Me.Label13)
        Me.GroupBox4.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox4.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox4.Location = New System.Drawing.Point(15, 588)
        Me.GroupBox4.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox4.Name = "GroupBox4"
        Me.GroupBox4.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox4.Size = New System.Drawing.Size(1099, 85)
        Me.GroupBox4.TabIndex = 28
        Me.GroupBox4.TabStop = False
        Me.GroupBox4.Text = "COMPUTATIONS"
        '
        'lblvat
        '
        Me.lblvat.AutoSize = True
        Me.lblvat.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblvat.ForeColor = System.Drawing.Color.Black
        Me.lblvat.Location = New System.Drawing.Point(903, 21)
        Me.lblvat.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblvat.Name = "lblvat"
        Me.lblvat.Size = New System.Drawing.Size(24, 28)
        Me.lblvat.TabIndex = 131
        Me.lblvat.Text = "0"
        '
        'lblscipsi
        '
        Me.lblscipsi.AutoSize = True
        Me.lblscipsi.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblscipsi.ForeColor = System.Drawing.Color.Black
        Me.lblscipsi.Location = New System.Drawing.Point(903, 49)
        Me.lblscipsi.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblscipsi.Name = "lblscipsi"
        Me.lblscipsi.Size = New System.Drawing.Size(24, 28)
        Me.lblscipsi.TabIndex = 130
        Me.lblscipsi.Text = "0"
        '
        'lblppa
        '
        Me.lblppa.AutoSize = True
        Me.lblppa.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblppa.ForeColor = System.Drawing.Color.Black
        Me.lblppa.Location = New System.Drawing.Point(222, 49)
        Me.lblppa.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblppa.Name = "lblppa"
        Me.lblppa.Size = New System.Drawing.Size(24, 28)
        Me.lblppa.TabIndex = 127
        Me.lblppa.Text = "0"
        '
        'lbltotal
        '
        Me.lbltotal.AutoSize = True
        Me.lbltotal.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbltotal.ForeColor = System.Drawing.Color.Black
        Me.lbltotal.Location = New System.Drawing.Point(222, 21)
        Me.lbltotal.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lbltotal.Name = "lbltotal"
        Me.lbltotal.Size = New System.Drawing.Size(24, 28)
        Me.lbltotal.TabIndex = 126
        Me.lbltotal.Text = "0"
        '
        'Label14
        '
        Me.Label14.AutoSize = True
        Me.Label14.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label14.ForeColor = System.Drawing.Color.Black
        Me.Label14.Location = New System.Drawing.Point(742, 49)
        Me.Label14.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label14.Name = "Label14"
        Me.Label14.Size = New System.Drawing.Size(153, 28)
        Me.Label14.TabIndex = 125
        Me.Label14.Text = "DUE TO SCIPSI:"
        '
        'Label15
        '
        Me.Label15.AutoSize = True
        Me.Label15.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label15.ForeColor = System.Drawing.Color.Black
        Me.Label15.Location = New System.Drawing.Point(742, 21)
        Me.Label15.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label15.Name = "Label15"
        Me.Label15.Size = New System.Drawing.Size(49, 28)
        Me.Label15.TabIndex = 124
        Me.Label15.Text = "VAT"
        '
        'Label9
        '
        Me.Label9.AutoSize = True
        Me.Label9.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label9.ForeColor = System.Drawing.Color.Black
        Me.Label9.Location = New System.Drawing.Point(41, 49)
        Me.Label9.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label9.Name = "Label9"
        Me.Label9.Size = New System.Drawing.Size(173, 28)
        Me.Label9.TabIndex = 121
        Me.Label9.Text = "LESS PPA SHARE:"
        '
        'Label5
        '
        Me.Label5.AutoSize = True
        Me.Label5.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label5.ForeColor = System.Drawing.Color.Black
        Me.Label5.Location = New System.Drawing.Point(41, 20)
        Me.Label5.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label5.Name = "Label5"
        Me.Label5.Size = New System.Drawing.Size(77, 28)
        Me.Label5.TabIndex = 120
        Me.Label5.Text = "TOTAL:"
        '
        'lblnet
        '
        Me.lblnet.AutoSize = True
        Me.lblnet.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblnet.ForeColor = System.Drawing.Color.Black
        Me.lblnet.Location = New System.Drawing.Point(496, 48)
        Me.lblnet.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblnet.Name = "lblnet"
        Me.lblnet.Size = New System.Drawing.Size(24, 28)
        Me.lblnet.TabIndex = 129
        Me.lblnet.Text = "0"
        '
        'lbldisc
        '
        Me.lbldisc.AutoSize = True
        Me.lbldisc.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbldisc.ForeColor = System.Drawing.Color.Black
        Me.lbldisc.Location = New System.Drawing.Point(496, 20)
        Me.lbldisc.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lbldisc.Name = "lbldisc"
        Me.lbldisc.Size = New System.Drawing.Size(24, 28)
        Me.lbldisc.TabIndex = 128
        Me.lbldisc.Text = "0"
        '
        'Label12
        '
        Me.Label12.AutoSize = True
        Me.Label12.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label12.ForeColor = System.Drawing.Color.Black
        Me.Label12.Location = New System.Drawing.Point(426, 49)
        Me.Label12.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label12.Name = "Label12"
        Me.Label12.Size = New System.Drawing.Size(56, 28)
        Me.Label12.TabIndex = 123
        Me.Label12.Text = "NET:"
        '
        'Label13
        '
        Me.Label13.AutoSize = True
        Me.Label13.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label13.ForeColor = System.Drawing.Color.Black
        Me.Label13.Location = New System.Drawing.Point(426, 21)
        Me.Label13.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label13.Name = "Label13"
        Me.Label13.Size = New System.Drawing.Size(61, 28)
        Me.Label13.TabIndex = 122
        Me.Label13.Text = "DISC:"
        '
        'GroupBox5
        '
        Me.GroupBox5.Controls.Add(Me.lblemp)
        Me.GroupBox5.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox5.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox5.Location = New System.Drawing.Point(715, 312)
        Me.GroupBox5.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox5.Name = "GroupBox5"
        Me.GroupBox5.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox5.Size = New System.Drawing.Size(399, 52)
        Me.GroupBox5.TabIndex = 29
        Me.GroupBox5.TabStop = False
        Me.GroupBox5.Text = "ENCODED BY:"
        '
        'lblemp
        '
        Me.lblemp.AutoSize = True
        Me.lblemp.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblemp.ForeColor = System.Drawing.Color.Black
        Me.lblemp.Location = New System.Drawing.Point(10, 17)
        Me.lblemp.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblemp.Name = "lblemp"
        Me.lblemp.Size = New System.Drawing.Size(0, 28)
        Me.lblemp.TabIndex = 120
        Me.lblemp.TextAlign = System.Drawing.ContentAlignment.MiddleCenter
        '
        'GroupBox6
        '
        Me.GroupBox6.Controls.Add(Me.SimpleButton2)
        Me.GroupBox6.Controls.Add(Me.SimpleButton1)
        Me.GroupBox6.Controls.Add(Me.DataGridView1)
        Me.GroupBox6.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox6.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox6.Location = New System.Drawing.Point(714, 16)
        Me.GroupBox6.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox6.Name = "GroupBox6"
        Me.GroupBox6.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox6.Size = New System.Drawing.Size(399, 291)
        Me.GroupBox6.TabIndex = 30
        Me.GroupBox6.TabStop = False
        Me.GroupBox6.Text = "REQUEST:"
        '
        'SimpleButton2
        '
        Me.SimpleButton2.Appearance.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.SimpleButton2.Appearance.Options.UseFont = True
        Me.SimpleButton2.Location = New System.Drawing.Point(315, 260)
        Me.SimpleButton2.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.SimpleButton2.Name = "SimpleButton2"
        Me.SimpleButton2.Size = New System.Drawing.Size(75, 26)
        Me.SimpleButton2.TabIndex = 21
        Me.SimpleButton2.Text = "Process"
        '
        'SimpleButton1
        '
        Me.SimpleButton1.Appearance.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.SimpleButton1.Appearance.Options.UseFont = True
        Me.SimpleButton1.Location = New System.Drawing.Point(9, 260)
        Me.SimpleButton1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.SimpleButton1.Name = "SimpleButton1"
        Me.SimpleButton1.Size = New System.Drawing.Size(75, 26)
        Me.SimpleButton1.TabIndex = 20
        Me.SimpleButton1.Text = "Refresh"
        '
        'DataGridView1
        '
        Me.DataGridView1.AllowUserToResizeRows = False
        Me.DataGridView1.BackgroundColor = System.Drawing.Color.White
        Me.DataGridView1.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.DataGridView1.CellBorderStyle = System.Windows.Forms.DataGridViewCellBorderStyle.None
        Me.DataGridView1.ColumnHeadersHeightSizeMode = System.Windows.Forms.DataGridViewColumnHeadersHeightSizeMode.AutoSize
        Me.DataGridView1.Columns.AddRange(New System.Windows.Forms.DataGridViewColumn() {Me.aa, Me.DataGridViewTextBoxColumn1, Me.DataGridViewTextBoxColumn2, Me.DataGridViewTextBoxColumn5, Me.DataGridViewTextBoxColumn4, Me.DataGridViewTextBoxColumn3})
        Me.DataGridView1.Location = New System.Drawing.Point(9, 18)
        Me.DataGridView1.MultiSelect = False
        Me.DataGridView1.Name = "DataGridView1"
        Me.DataGridView1.ReadOnly = True
        Me.DataGridView1.RowHeadersVisible = False
        Me.DataGridView1.RowTemplate.Height = 24
        Me.DataGridView1.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.FullRowSelect
        Me.DataGridView1.Size = New System.Drawing.Size(382, 235)
        Me.DataGridView1.TabIndex = 2
        '
        'aa
        '
        Me.aa.DataPropertyName = "rb_id"
        Me.aa.HeaderText = "id"
        Me.aa.Name = "aa"
        Me.aa.ReadOnly = True
        Me.aa.Visible = False
        '
        'DataGridViewTextBoxColumn1
        '
        Me.DataGridViewTextBoxColumn1.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.DataGridViewTextBoxColumn1.DataPropertyName = "rb_num"
        Me.DataGridViewTextBoxColumn1.HeaderText = "BILL #"
        Me.DataGridViewTextBoxColumn1.Name = "DataGridViewTextBoxColumn1"
        Me.DataGridViewTextBoxColumn1.ReadOnly = True
        '
        'DataGridViewTextBoxColumn2
        '
        Me.DataGridViewTextBoxColumn2.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.DataGridViewTextBoxColumn2.DataPropertyName = "rb_type"
        Me.DataGridViewTextBoxColumn2.HeaderText = "REQUEST TYPE"
        Me.DataGridViewTextBoxColumn2.Name = "DataGridViewTextBoxColumn2"
        Me.DataGridViewTextBoxColumn2.ReadOnly = True
        '
        'DataGridViewTextBoxColumn5
        '
        Me.DataGridViewTextBoxColumn5.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.DataGridViewTextBoxColumn5.DataPropertyName = "rb_status"
        Me.DataGridViewTextBoxColumn5.HeaderText = "STATUS"
        Me.DataGridViewTextBoxColumn5.Name = "DataGridViewTextBoxColumn5"
        Me.DataGridViewTextBoxColumn5.ReadOnly = True
        '
        'DataGridViewTextBoxColumn4
        '
        Me.DataGridViewTextBoxColumn4.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.DataGridViewTextBoxColumn4.DataPropertyName = "rb_reqby"
        Me.DataGridViewTextBoxColumn4.HeaderText = "REQUEST BY"
        Me.DataGridViewTextBoxColumn4.Name = "DataGridViewTextBoxColumn4"
        Me.DataGridViewTextBoxColumn4.ReadOnly = True
        '
        'DataGridViewTextBoxColumn3
        '
        Me.DataGridViewTextBoxColumn3.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.DataGridViewTextBoxColumn3.DataPropertyName = "rb_reason"
        Me.DataGridViewTextBoxColumn3.HeaderText = "REASON"
        Me.DataGridViewTextBoxColumn3.Name = "DataGridViewTextBoxColumn3"
        Me.DataGridViewTextBoxColumn3.ReadOnly = True
        '
        'frmbilltrans
        '
        Me.Appearance.BackColor = System.Drawing.Color.White
        Me.Appearance.Options.UseBackColor = True
        Me.Appearance.Options.UseFont = True
        Me.AutoScaleDimensions = New System.Drawing.SizeF(7.0!, 15.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(1128, 681)
        Me.Controls.Add(Me.GroupBox6)
        Me.Controls.Add(Me.GroupBox4)
        Me.Controls.Add(Me.GroupBox5)
        Me.Controls.Add(Me.GroupBox2)
        Me.Controls.Add(Me.GroupBox1)
        Me.Font = New System.Drawing.Font("Segoe UI", 7.2!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Name = "frmbilltrans"
        Me.Text = "BILL TRANSACTION"
        Me.GroupBox2.ResumeLayout(False)
        CType(Me.dgbill, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox1.ResumeLayout(False)
        Me.GroupBox1.PerformLayout()
        CType(Me.chkper.Properties, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox3.ResumeLayout(False)
        CType(Me.lbldatetrans.Properties.CalendarTimeProperties, System.ComponentModel.ISupportInitialize).EndInit()
        CType(Me.lbldatetrans.Properties, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox4.ResumeLayout(False)
        Me.GroupBox4.PerformLayout()
        Me.GroupBox5.ResumeLayout(False)
        Me.GroupBox5.PerformLayout()
        Me.GroupBox6.ResumeLayout(False)
        CType(Me.DataGridView1, System.ComponentModel.ISupportInitialize).EndInit()
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents GroupBox2 As System.Windows.Forms.GroupBox
    Friend WithEvents dgbill As System.Windows.Forms.DataGridView
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents LabelControl5 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents txtbillnum As System.Windows.Forms.TextBox
    Friend WithEvents txtaccname As System.Windows.Forms.TextBox
    Friend WithEvents Label11 As System.Windows.Forms.Label
    Friend WithEvents Label8 As System.Windows.Forms.Label
    Friend WithEvents Label7 As System.Windows.Forms.Label
    Friend WithEvents Label6 As System.Windows.Forms.Label
    Friend WithEvents Label4 As System.Windows.Forms.Label
    Friend WithEvents lbldatetrans As DevExpress.XtraEditors.DateEdit
    Friend WithEvents txtParticulars As System.Windows.Forms.TextBox
    Friend WithEvents Label10 As System.Windows.Forms.Label
    Friend WithEvents txtVessel As System.Windows.Forms.TextBox
    Friend WithEvents cbType As System.Windows.Forms.ComboBox
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents txtVoyage As System.Windows.Forms.MaskedTextBox
    Friend WithEvents txtAccountno As System.Windows.Forms.MaskedTextBox
    Friend WithEvents cbRoute As System.Windows.Forms.ComboBox
    Friend WithEvents GroupBox3 As System.Windows.Forms.GroupBox
    Friend WithEvents btn1 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents Label3 As System.Windows.Forms.Label
    Friend WithEvents cbstatus As System.Windows.Forms.ComboBox
    Friend WithEvents cbprint As System.Windows.Forms.ComboBox
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents GroupBox4 As System.Windows.Forms.GroupBox
    Friend WithEvents lblvat As System.Windows.Forms.Label
    Friend WithEvents lblscipsi As System.Windows.Forms.Label
    Friend WithEvents lblnet As System.Windows.Forms.Label
    Friend WithEvents lbldisc As System.Windows.Forms.Label
    Friend WithEvents lblppa As System.Windows.Forms.Label
    Friend WithEvents lbltotal As System.Windows.Forms.Label
    Friend WithEvents Label14 As System.Windows.Forms.Label
    Friend WithEvents Label15 As System.Windows.Forms.Label
    Friend WithEvents Label12 As System.Windows.Forms.Label
    Friend WithEvents Label13 As System.Windows.Forms.Label
    Friend WithEvents Label9 As System.Windows.Forms.Label
    Friend WithEvents Label5 As System.Windows.Forms.Label
    Friend WithEvents btn2 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents GroupBox5 As System.Windows.Forms.GroupBox
    Friend WithEvents lblemp As System.Windows.Forms.Label
    Friend WithEvents Column2 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column1 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column3 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column4 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column5 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column6 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column7 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Label16 As System.Windows.Forms.Label
    Friend WithEvents txtperiod As System.Windows.Forms.TextBox
    Friend WithEvents chkper As DevExpress.XtraEditors.CheckEdit
    Friend WithEvents GroupBox6 As System.Windows.Forms.GroupBox
    Friend WithEvents SimpleButton2 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents SimpleButton1 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents DataGridView1 As System.Windows.Forms.DataGridView
    Friend WithEvents aa As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents DataGridViewTextBoxColumn1 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents DataGridViewTextBoxColumn2 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents DataGridViewTextBoxColumn5 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents DataGridViewTextBoxColumn4 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents DataGridViewTextBoxColumn3 As System.Windows.Forms.DataGridViewTextBoxColumn
End Class
