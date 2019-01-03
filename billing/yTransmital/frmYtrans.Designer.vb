<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmYtrans
    Inherits System.Windows.Forms.Form

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        Try
            If disposing AndAlso components IsNot Nothing Then
                components.Dispose()
            End If
        Finally
            MyBase.Dispose(disposing)
        End Try
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Dim DataGridViewCellStyle6 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle7 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle8 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle9 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle10 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Me.GroupBox3 = New System.Windows.Forms.GroupBox()
        Me.dgYtransmittal = New System.Windows.Forms.DataGridView()
        Me.Column1 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column6 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column2 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column7 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column3 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column4 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column5 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column8 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.GroupBox2 = New System.Windows.Forms.GroupBox()
        Me.txtBilldate = New System.Windows.Forms.DateTimePicker()
        Me.txtYtransno = New System.Windows.Forms.MaskedTextBox()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.GroupBox1 = New System.Windows.Forms.GroupBox()
        Me.btnEdit = New System.Windows.Forms.Button()
        Me.btnSearchTrans = New System.Windows.Forms.Button()
        Me.btnPrintTrans = New System.Windows.Forms.Button()
        Me.GroupBox5 = New System.Windows.Forms.GroupBox()
        Me.txtNotice = New System.Windows.Forms.TextBox()
        Me.PictureBox1 = New System.Windows.Forms.PictureBox()
        Me.btnDelTrans = New System.Windows.Forms.Button()
        Me.btnDelBill = New System.Windows.Forms.Button()
        Me.btnAddBill = New System.Windows.Forms.Button()
        Me.btnNewTrans = New System.Windows.Forms.Button()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.txtindi1 = New System.Windows.Forms.TextBox()
        Me.txtindi = New System.Windows.Forms.TextBox()
        Me.btnClose = New System.Windows.Forms.Button()
        Me.GroupBox3.SuspendLayout()
        CType(Me.dgYtransmittal, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox2.SuspendLayout()
        Me.GroupBox1.SuspendLayout()
        Me.GroupBox5.SuspendLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.SuspendLayout()
        '
        'GroupBox3
        '
        Me.GroupBox3.Controls.Add(Me.dgYtransmittal)
        Me.GroupBox3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox3.Location = New System.Drawing.Point(12, 212)
        Me.GroupBox3.Name = "GroupBox3"
        Me.GroupBox3.Size = New System.Drawing.Size(718, 387)
        Me.GroupBox3.TabIndex = 33
        Me.GroupBox3.TabStop = False
        Me.GroupBox3.Text = "CURRENT CHARGES"
        '
        'dgYtransmittal
        '
        Me.dgYtransmittal.AllowUserToAddRows = False
        Me.dgYtransmittal.AllowUserToDeleteRows = False
        Me.dgYtransmittal.AllowUserToResizeColumns = False
        Me.dgYtransmittal.AllowUserToResizeRows = False
        DataGridViewCellStyle6.BackColor = System.Drawing.Color.Aqua
        DataGridViewCellStyle6.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.dgYtransmittal.AlternatingRowsDefaultCellStyle = DataGridViewCellStyle6
        Me.dgYtransmittal.AutoSizeRowsMode = System.Windows.Forms.DataGridViewAutoSizeRowsMode.AllCellsExceptHeaders
        Me.dgYtransmittal.BackgroundColor = System.Drawing.Color.White
        Me.dgYtransmittal.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.dgYtransmittal.CellBorderStyle = System.Windows.Forms.DataGridViewCellBorderStyle.SunkenHorizontal
        DataGridViewCellStyle7.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle7.BackColor = System.Drawing.Color.Black
        DataGridViewCellStyle7.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle7.ForeColor = System.Drawing.Color.Yellow
        DataGridViewCellStyle7.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle7.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle7.WrapMode = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgYtransmittal.ColumnHeadersDefaultCellStyle = DataGridViewCellStyle7
        Me.dgYtransmittal.ColumnHeadersHeightSizeMode = System.Windows.Forms.DataGridViewColumnHeadersHeightSizeMode.AutoSize
        Me.dgYtransmittal.Columns.AddRange(New System.Windows.Forms.DataGridViewColumn() {Me.Column1, Me.Column6, Me.Column2, Me.Column7, Me.Column3, Me.Column4, Me.Column5, Me.Column8})
        DataGridViewCellStyle8.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle8.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle8.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle8.ForeColor = System.Drawing.Color.Black
        DataGridViewCellStyle8.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle8.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle8.WrapMode = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgYtransmittal.DefaultCellStyle = DataGridViewCellStyle8
        Me.dgYtransmittal.EditMode = System.Windows.Forms.DataGridViewEditMode.EditProgrammatically
        Me.dgYtransmittal.ImeMode = System.Windows.Forms.ImeMode.Disable
        Me.dgYtransmittal.Location = New System.Drawing.Point(9, 21)
        Me.dgYtransmittal.MultiSelect = False
        Me.dgYtransmittal.Name = "dgYtransmittal"
        Me.dgYtransmittal.ReadOnly = True
        Me.dgYtransmittal.RowHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.Sunken
        DataGridViewCellStyle9.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle9.BackColor = System.Drawing.Color.Black
        DataGridViewCellStyle9.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle9.ForeColor = System.Drawing.Color.Yellow
        DataGridViewCellStyle9.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle9.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        Me.dgYtransmittal.RowHeadersDefaultCellStyle = DataGridViewCellStyle9
        Me.dgYtransmittal.RowHeadersVisible = False
        Me.dgYtransmittal.RowHeadersWidthSizeMode = System.Windows.Forms.DataGridViewRowHeadersWidthSizeMode.DisableResizing
        DataGridViewCellStyle10.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle10.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle10.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle10.ForeColor = System.Drawing.Color.Black
        Me.dgYtransmittal.RowsDefaultCellStyle = DataGridViewCellStyle10
        Me.dgYtransmittal.ScrollBars = System.Windows.Forms.ScrollBars.Vertical
        Me.dgYtransmittal.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.FullRowSelect
        Me.dgYtransmittal.ShowCellErrors = False
        Me.dgYtransmittal.ShowCellToolTips = False
        Me.dgYtransmittal.ShowEditingIcon = False
        Me.dgYtransmittal.ShowRowErrors = False
        Me.dgYtransmittal.Size = New System.Drawing.Size(699, 351)
        Me.dgYtransmittal.TabIndex = 1
        Me.dgYtransmittal.TabStop = False
        '
        'Column1
        '
        Me.Column1.DataPropertyName = "yi_date"
        Me.Column1.HeaderText = "DATE"
        Me.Column1.Name = "Column1"
        Me.Column1.ReadOnly = True
        Me.Column1.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column1.Width = 80
        '
        'Column6
        '
        Me.Column6.DataPropertyName = "yi_billnum"
        Me.Column6.HeaderText = "BILL #"
        Me.Column6.Name = "Column6"
        Me.Column6.ReadOnly = True
        Me.Column6.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column6.Width = 80
        '
        'Column2
        '
        Me.Column2.DataPropertyName = "yi_particulars"
        Me.Column2.HeaderText = "PARTICULARS"
        Me.Column2.Name = "Column2"
        Me.Column2.ReadOnly = True
        Me.Column2.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column2.Width = 120
        '
        'Column7
        '
        Me.Column7.DataPropertyName = "yi_type"
        Me.Column7.HeaderText = "I/O"
        Me.Column7.Name = "Column7"
        Me.Column7.ReadOnly = True
        Me.Column7.Width = 40
        '
        'Column3
        '
        Me.Column3.DataPropertyName = "yi_vessel"
        Me.Column3.HeaderText = "VESSEL"
        Me.Column3.Name = "Column3"
        Me.Column3.ReadOnly = True
        Me.Column3.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column3.Width = 150
        '
        'Column4
        '
        Me.Column4.DataPropertyName = "yi_voyage"
        Me.Column4.HeaderText = "VOYAGE"
        Me.Column4.Name = "Column4"
        Me.Column4.ReadOnly = True
        Me.Column4.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column4.Width = 70
        '
        'Column5
        '
        Me.Column5.DataPropertyName = "yi_cusname"
        Me.Column5.HeaderText = "SHIPPING LINES"
        Me.Column5.Name = "Column5"
        Me.Column5.ReadOnly = True
        Me.Column5.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column5.Width = 159
        '
        'Column8
        '
        Me.Column8.DataPropertyName = "yi_id"
        Me.Column8.HeaderText = "id"
        Me.Column8.Name = "Column8"
        Me.Column8.ReadOnly = True
        Me.Column8.Visible = False
        '
        'GroupBox2
        '
        Me.GroupBox2.Controls.Add(Me.txtBilldate)
        Me.GroupBox2.Controls.Add(Me.txtYtransno)
        Me.GroupBox2.Controls.Add(Me.Label3)
        Me.GroupBox2.Controls.Add(Me.Label2)
        Me.GroupBox2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox2.Location = New System.Drawing.Point(12, 141)
        Me.GroupBox2.Name = "GroupBox2"
        Me.GroupBox2.Size = New System.Drawing.Size(718, 65)
        Me.GroupBox2.TabIndex = 32
        Me.GroupBox2.TabStop = False
        Me.GroupBox2.Text = "INFO"
        '
        'txtBilldate
        '
        Me.txtBilldate.Enabled = False
        Me.txtBilldate.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtBilldate.Format = System.Windows.Forms.DateTimePickerFormat.[Short]
        Me.txtBilldate.Location = New System.Drawing.Point(114, 35)
        Me.txtBilldate.MinDate = New Date(2015, 1, 1, 0, 0, 0, 0)
        Me.txtBilldate.Name = "txtBilldate"
        Me.txtBilldate.Size = New System.Drawing.Size(83, 22)
        Me.txtBilldate.TabIndex = 2
        Me.txtBilldate.Value = New Date(2015, 1, 24, 0, 0, 0, 0)
        '
        'txtYtransno
        '
        Me.txtYtransno.BackColor = System.Drawing.Color.White
        Me.txtYtransno.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtYtransno.Enabled = False
        Me.txtYtransno.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtYtransno.ForeColor = System.Drawing.Color.Purple
        Me.txtYtransno.Location = New System.Drawing.Point(106, 16)
        Me.txtYtransno.Mask = "########"
        Me.txtYtransno.Name = "txtYtransno"
        Me.txtYtransno.Size = New System.Drawing.Size(91, 15)
        Me.txtYtransno.TabIndex = 1
        Me.txtYtransno.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.Location = New System.Drawing.Point(6, 42)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(36, 13)
        Me.Label3.TabIndex = 27
        Me.Label3.Text = "DATE:"
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(6, 16)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(88, 13)
        Me.Label2.TabIndex = 26
        Me.Label2.Text = "TRANSMITTAL #:"
        '
        'GroupBox1
        '
        Me.GroupBox1.Controls.Add(Me.btnEdit)
        Me.GroupBox1.Controls.Add(Me.btnSearchTrans)
        Me.GroupBox1.Controls.Add(Me.btnPrintTrans)
        Me.GroupBox1.Controls.Add(Me.GroupBox5)
        Me.GroupBox1.Controls.Add(Me.btnDelTrans)
        Me.GroupBox1.Controls.Add(Me.btnDelBill)
        Me.GroupBox1.Controls.Add(Me.btnAddBill)
        Me.GroupBox1.Controls.Add(Me.btnNewTrans)
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.Location = New System.Drawing.Point(12, 54)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Size = New System.Drawing.Size(718, 81)
        Me.GroupBox1.TabIndex = 31
        Me.GroupBox1.TabStop = False
        Me.GroupBox1.Text = "FUNCTIONS"
        '
        'btnEdit
        '
        Me.btnEdit.BackColor = System.Drawing.Color.Transparent
        Me.btnEdit.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnEdit.Enabled = False
        Me.btnEdit.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnEdit.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnEdit.Image = Global.billing.My.Resources.Resources.edit_property_26
        Me.btnEdit.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnEdit.Location = New System.Drawing.Point(462, 15)
        Me.btnEdit.Name = "btnEdit"
        Me.btnEdit.Size = New System.Drawing.Size(70, 60)
        Me.btnEdit.TabIndex = 34
        Me.btnEdit.TabStop = False
        Me.btnEdit.Text = "F7 EDIT TRANSMIT."
        Me.btnEdit.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnEdit.UseVisualStyleBackColor = False
        '
        'btnSearchTrans
        '
        Me.btnSearchTrans.BackColor = System.Drawing.Color.Transparent
        Me.btnSearchTrans.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnSearchTrans.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnSearchTrans.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnSearchTrans.Image = Global.billing.My.Resources.Resources.search_26
        Me.btnSearchTrans.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnSearchTrans.Location = New System.Drawing.Point(386, 15)
        Me.btnSearchTrans.Name = "btnSearchTrans"
        Me.btnSearchTrans.Size = New System.Drawing.Size(70, 60)
        Me.btnSearchTrans.TabIndex = 33
        Me.btnSearchTrans.TabStop = False
        Me.btnSearchTrans.Text = "F6 SEARCH TRASMIT."
        Me.btnSearchTrans.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnSearchTrans.UseVisualStyleBackColor = False
        '
        'btnPrintTrans
        '
        Me.btnPrintTrans.BackColor = System.Drawing.Color.Transparent
        Me.btnPrintTrans.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnPrintTrans.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnPrintTrans.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnPrintTrans.Image = Global.billing.My.Resources.Resources.print_26
        Me.btnPrintTrans.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnPrintTrans.Location = New System.Drawing.Point(234, 15)
        Me.btnPrintTrans.Name = "btnPrintTrans"
        Me.btnPrintTrans.Size = New System.Drawing.Size(70, 60)
        Me.btnPrintTrans.TabIndex = 32
        Me.btnPrintTrans.TabStop = False
        Me.btnPrintTrans.Text = "F4 PRINT TRANSMIT."
        Me.btnPrintTrans.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnPrintTrans.UseVisualStyleBackColor = False
        '
        'GroupBox5
        '
        Me.GroupBox5.Controls.Add(Me.txtNotice)
        Me.GroupBox5.Controls.Add(Me.PictureBox1)
        Me.GroupBox5.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox5.Location = New System.Drawing.Point(549, 15)
        Me.GroupBox5.Name = "GroupBox5"
        Me.GroupBox5.Size = New System.Drawing.Size(159, 60)
        Me.GroupBox5.TabIndex = 31
        Me.GroupBox5.TabStop = False
        Me.GroupBox5.Text = "NOTICE"
        '
        'txtNotice
        '
        Me.txtNotice.BackColor = System.Drawing.Color.White
        Me.txtNotice.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtNotice.Font = New System.Drawing.Font("Segoe UI", 9.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtNotice.ForeColor = System.Drawing.Color.Maroon
        Me.txtNotice.Location = New System.Drawing.Point(6, 19)
        Me.txtNotice.Multiline = True
        Me.txtNotice.Name = "txtNotice"
        Me.txtNotice.ReadOnly = True
        Me.txtNotice.Size = New System.Drawing.Size(112, 35)
        Me.txtNotice.TabIndex = 32
        Me.txtNotice.TabStop = False
        '
        'PictureBox1
        '
        Me.PictureBox1.Image = Global.billing.My.Resources.Resources.message_26
        Me.PictureBox1.Location = New System.Drawing.Point(124, 19)
        Me.PictureBox1.Name = "PictureBox1"
        Me.PictureBox1.Size = New System.Drawing.Size(28, 25)
        Me.PictureBox1.TabIndex = 31
        Me.PictureBox1.TabStop = False
        '
        'btnDelTrans
        '
        Me.btnDelTrans.BackColor = System.Drawing.Color.Transparent
        Me.btnDelTrans.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnDelTrans.Enabled = False
        Me.btnDelTrans.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnDelTrans.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnDelTrans.Image = Global.billing.My.Resources.Resources.delete_26
        Me.btnDelTrans.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnDelTrans.Location = New System.Drawing.Point(310, 15)
        Me.btnDelTrans.Name = "btnDelTrans"
        Me.btnDelTrans.Size = New System.Drawing.Size(70, 60)
        Me.btnDelTrans.TabIndex = 10
        Me.btnDelTrans.TabStop = False
        Me.btnDelTrans.Text = "F5 DELETE TRANSMIT."
        Me.btnDelTrans.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnDelTrans.UseVisualStyleBackColor = False
        '
        'btnDelBill
        '
        Me.btnDelBill.BackColor = System.Drawing.Color.Transparent
        Me.btnDelBill.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnDelBill.Enabled = False
        Me.btnDelBill.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnDelBill.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnDelBill.Image = Global.billing.My.Resources.Resources.delete_property_26
        Me.btnDelBill.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnDelBill.Location = New System.Drawing.Point(158, 15)
        Me.btnDelBill.Name = "btnDelBill"
        Me.btnDelBill.Size = New System.Drawing.Size(70, 60)
        Me.btnDelBill.TabIndex = 9
        Me.btnDelBill.TabStop = False
        Me.btnDelBill.Text = "F3 DELETE BILL"
        Me.btnDelBill.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnDelBill.UseVisualStyleBackColor = False
        '
        'btnAddBill
        '
        Me.btnAddBill.BackColor = System.Drawing.Color.Transparent
        Me.btnAddBill.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnAddBill.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnAddBill.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnAddBill.Image = Global.billing.My.Resources.Resources.add_list_26
        Me.btnAddBill.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnAddBill.Location = New System.Drawing.Point(82, 15)
        Me.btnAddBill.Name = "btnAddBill"
        Me.btnAddBill.Size = New System.Drawing.Size(70, 60)
        Me.btnAddBill.TabIndex = 8
        Me.btnAddBill.TabStop = False
        Me.btnAddBill.Text = "F2 ADD BILL"
        Me.btnAddBill.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnAddBill.UseVisualStyleBackColor = False
        '
        'btnNewTrans
        '
        Me.btnNewTrans.BackColor = System.Drawing.Color.Transparent
        Me.btnNewTrans.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnNewTrans.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnNewTrans.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnNewTrans.Image = Global.billing.My.Resources.Resources.google_news_26
        Me.btnNewTrans.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnNewTrans.Location = New System.Drawing.Point(6, 15)
        Me.btnNewTrans.Name = "btnNewTrans"
        Me.btnNewTrans.Size = New System.Drawing.Size(70, 60)
        Me.btnNewTrans.TabIndex = 7
        Me.btnNewTrans.TabStop = False
        Me.btnNewTrans.Text = "F1 NEW TRANSMIT."
        Me.btnNewTrans.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnNewTrans.UseVisualStyleBackColor = False
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Font = New System.Drawing.Font("Segoe UI", 15.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(248, 8)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(247, 30)
        Me.Label1.TabIndex = 30
        Me.Label1.Text = "YELLOW TRANSMITTAL"
        '
        'txtindi1
        '
        Me.txtindi1.Location = New System.Drawing.Point(118, 13)
        Me.txtindi1.Name = "txtindi1"
        Me.txtindi1.Size = New System.Drawing.Size(100, 20)
        Me.txtindi1.TabIndex = 35
        Me.txtindi1.Text = "0"
        Me.txtindi1.Visible = False
        '
        'txtindi
        '
        Me.txtindi.Location = New System.Drawing.Point(12, 14)
        Me.txtindi.Name = "txtindi"
        Me.txtindi.Size = New System.Drawing.Size(100, 20)
        Me.txtindi.TabIndex = 34
        Me.txtindi.Text = "1"
        Me.txtindi.Visible = False
        '
        'btnClose
        '
        Me.btnClose.BackColor = System.Drawing.Color.Transparent
        Me.btnClose.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnClose.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnClose.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnClose.Location = New System.Drawing.Point(723, -2)
        Me.btnClose.Name = "btnClose"
        Me.btnClose.Size = New System.Drawing.Size(22, 20)
        Me.btnClose.TabIndex = 36
        Me.btnClose.TabStop = False
        Me.btnClose.Text = "X"
        Me.btnClose.UseVisualStyleBackColor = False
        '
        'frmYtrans
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(6.0!, 13.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.BackColor = System.Drawing.Color.White
        Me.ClientSize = New System.Drawing.Size(742, 611)
        Me.ControlBox = False
        Me.Controls.Add(Me.btnClose)
        Me.Controls.Add(Me.txtindi1)
        Me.Controls.Add(Me.txtindi)
        Me.Controls.Add(Me.GroupBox3)
        Me.Controls.Add(Me.GroupBox2)
        Me.Controls.Add(Me.GroupBox1)
        Me.Controls.Add(Me.Label1)
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.None
        Me.Name = "frmYtrans"
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterParent
        Me.Text = "YELLOW BILL TRANSMITAL FORM"
        Me.GroupBox3.ResumeLayout(False)
        CType(Me.dgYtransmittal, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox2.ResumeLayout(False)
        Me.GroupBox2.PerformLayout()
        Me.GroupBox1.ResumeLayout(False)
        Me.GroupBox5.ResumeLayout(False)
        Me.GroupBox5.PerformLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).EndInit()
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents GroupBox3 As System.Windows.Forms.GroupBox
    Friend WithEvents dgYtransmittal As System.Windows.Forms.DataGridView
    Friend WithEvents GroupBox2 As System.Windows.Forms.GroupBox
    Friend WithEvents txtBilldate As System.Windows.Forms.DateTimePicker
    Friend WithEvents txtYtransno As System.Windows.Forms.MaskedTextBox
    Friend WithEvents Label3 As System.Windows.Forms.Label
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents btnEdit As System.Windows.Forms.Button
    Friend WithEvents btnSearchTrans As System.Windows.Forms.Button
    Friend WithEvents btnPrintTrans As System.Windows.Forms.Button
    Friend WithEvents GroupBox5 As System.Windows.Forms.GroupBox
    Friend WithEvents txtNotice As System.Windows.Forms.TextBox
    Friend WithEvents PictureBox1 As System.Windows.Forms.PictureBox
    Friend WithEvents btnDelTrans As System.Windows.Forms.Button
    Friend WithEvents btnDelBill As System.Windows.Forms.Button
    Friend WithEvents btnAddBill As System.Windows.Forms.Button
    Friend WithEvents btnNewTrans As System.Windows.Forms.Button
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents txtindi1 As System.Windows.Forms.TextBox
    Friend WithEvents txtindi As System.Windows.Forms.TextBox
    Friend WithEvents btnClose As System.Windows.Forms.Button
    Friend WithEvents Column1 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column6 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column2 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column7 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column3 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column4 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column5 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column8 As System.Windows.Forms.DataGridViewTextBoxColumn
End Class
