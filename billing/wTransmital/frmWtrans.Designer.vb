<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmWtrans
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
        Dim DataGridViewCellStyle1 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle2 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle3 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle4 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle5 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Me.btnClose = New System.Windows.Forms.Button()
        Me.txtindi1 = New System.Windows.Forms.TextBox()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.txtNotice = New System.Windows.Forms.TextBox()
        Me.GroupBox5 = New System.Windows.Forms.GroupBox()
        Me.PictureBox1 = New System.Windows.Forms.PictureBox()
        Me.txtBilldate = New System.Windows.Forms.DateTimePicker()
        Me.txtWtransno = New System.Windows.Forms.MaskedTextBox()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.txtindi = New System.Windows.Forms.TextBox()
        Me.GroupBox2 = New System.Windows.Forms.GroupBox()
        Me.dgWtransmittal = New System.Windows.Forms.DataGridView()
        Me.GroupBox3 = New System.Windows.Forms.GroupBox()
        Me.GroupBox1 = New System.Windows.Forms.GroupBox()
        Me.btnEdit = New System.Windows.Forms.Button()
        Me.btnSearchTrans = New System.Windows.Forms.Button()
        Me.btnPrintTrans = New System.Windows.Forms.Button()
        Me.btnDelTrans = New System.Windows.Forms.Button()
        Me.btnDelOr = New System.Windows.Forms.Button()
        Me.btnAddOr = New System.Windows.Forms.Button()
        Me.btnNewTrans = New System.Windows.Forms.Button()
        Me.Column8 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column1 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column2 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column3 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column5 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column6 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column7 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column9 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.GroupBox5.SuspendLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox2.SuspendLayout()
        CType(Me.dgWtransmittal, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox3.SuspendLayout()
        Me.GroupBox1.SuspendLayout()
        Me.SuspendLayout()
        '
        'btnClose
        '
        Me.btnClose.BackColor = System.Drawing.Color.Transparent
        Me.btnClose.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnClose.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnClose.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnClose.Location = New System.Drawing.Point(964, -1)
        Me.btnClose.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.btnClose.Name = "btnClose"
        Me.btnClose.Size = New System.Drawing.Size(29, 25)
        Me.btnClose.TabIndex = 43
        Me.btnClose.TabStop = False
        Me.btnClose.Text = "X"
        Me.btnClose.UseVisualStyleBackColor = False
        '
        'txtindi1
        '
        Me.txtindi1.Location = New System.Drawing.Point(157, 17)
        Me.txtindi1.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.txtindi1.Name = "txtindi1"
        Me.txtindi1.Size = New System.Drawing.Size(132, 22)
        Me.txtindi1.TabIndex = 42
        Me.txtindi1.Text = "0"
        Me.txtindi1.Visible = False
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Font = New System.Drawing.Font("Segoe UI", 15.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(265, 11)
        Me.Label1.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(440, 37)
        Me.Label1.TabIndex = 37
        Me.Label1.Text = "W/ HOLDING TAX TRANSMITTAL"
        '
        'txtNotice
        '
        Me.txtNotice.BackColor = System.Drawing.Color.White
        Me.txtNotice.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtNotice.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtNotice.ForeColor = System.Drawing.Color.Maroon
        Me.txtNotice.Location = New System.Drawing.Point(8, 23)
        Me.txtNotice.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.txtNotice.Multiline = True
        Me.txtNotice.Name = "txtNotice"
        Me.txtNotice.ReadOnly = True
        Me.txtNotice.Size = New System.Drawing.Size(149, 43)
        Me.txtNotice.TabIndex = 32
        Me.txtNotice.TabStop = False
        '
        'GroupBox5
        '
        Me.GroupBox5.Controls.Add(Me.txtNotice)
        Me.GroupBox5.Controls.Add(Me.PictureBox1)
        Me.GroupBox5.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox5.Location = New System.Drawing.Point(732, 18)
        Me.GroupBox5.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.GroupBox5.Name = "GroupBox5"
        Me.GroupBox5.Padding = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.GroupBox5.Size = New System.Drawing.Size(212, 74)
        Me.GroupBox5.TabIndex = 31
        Me.GroupBox5.TabStop = False
        Me.GroupBox5.Text = "NOTICE"
        '
        'PictureBox1
        '
        Me.PictureBox1.Image = Global.billing.My.Resources.Resources.message_26
        Me.PictureBox1.Location = New System.Drawing.Point(165, 23)
        Me.PictureBox1.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.PictureBox1.Name = "PictureBox1"
        Me.PictureBox1.Size = New System.Drawing.Size(37, 31)
        Me.PictureBox1.TabIndex = 31
        Me.PictureBox1.TabStop = False
        '
        'txtBilldate
        '
        Me.txtBilldate.Enabled = False
        Me.txtBilldate.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtBilldate.Format = System.Windows.Forms.DateTimePickerFormat.[Short]
        Me.txtBilldate.Location = New System.Drawing.Point(152, 43)
        Me.txtBilldate.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.txtBilldate.MinDate = New Date(2015, 1, 1, 0, 0, 0, 0)
        Me.txtBilldate.Name = "txtBilldate"
        Me.txtBilldate.Size = New System.Drawing.Size(109, 26)
        Me.txtBilldate.TabIndex = 2
        Me.txtBilldate.Value = New Date(2015, 1, 24, 0, 0, 0, 0)
        '
        'txtWtransno
        '
        Me.txtWtransno.BackColor = System.Drawing.Color.White
        Me.txtWtransno.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtWtransno.Enabled = False
        Me.txtWtransno.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtWtransno.ForeColor = System.Drawing.Color.Purple
        Me.txtWtransno.Location = New System.Drawing.Point(141, 20)
        Me.txtWtransno.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.txtWtransno.Mask = "########"
        Me.txtWtransno.Name = "txtWtransno"
        Me.txtWtransno.Size = New System.Drawing.Size(121, 19)
        Me.txtWtransno.TabIndex = 1
        Me.txtWtransno.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.Location = New System.Drawing.Point(8, 50)
        Me.Label3.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(45, 19)
        Me.Label3.TabIndex = 27
        Me.Label3.Text = "DATE:"
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(8, 20)
        Me.Label2.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(112, 19)
        Me.Label2.TabIndex = 26
        Me.Label2.Text = "TRANSMITTAL #:"
        '
        'txtindi
        '
        Me.txtindi.Location = New System.Drawing.Point(16, 18)
        Me.txtindi.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.txtindi.Name = "txtindi"
        Me.txtindi.Size = New System.Drawing.Size(132, 22)
        Me.txtindi.TabIndex = 41
        Me.txtindi.Text = "1"
        Me.txtindi.Visible = False
        '
        'GroupBox2
        '
        Me.GroupBox2.Controls.Add(Me.txtBilldate)
        Me.GroupBox2.Controls.Add(Me.txtWtransno)
        Me.GroupBox2.Controls.Add(Me.Label3)
        Me.GroupBox2.Controls.Add(Me.Label2)
        Me.GroupBox2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox2.Location = New System.Drawing.Point(16, 175)
        Me.GroupBox2.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.GroupBox2.Name = "GroupBox2"
        Me.GroupBox2.Padding = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.GroupBox2.Size = New System.Drawing.Size(957, 80)
        Me.GroupBox2.TabIndex = 39
        Me.GroupBox2.TabStop = False
        Me.GroupBox2.Text = "INFO"
        '
        'dgWtransmittal
        '
        Me.dgWtransmittal.AllowUserToAddRows = False
        Me.dgWtransmittal.AllowUserToDeleteRows = False
        Me.dgWtransmittal.AllowUserToResizeColumns = False
        Me.dgWtransmittal.AllowUserToResizeRows = False
        DataGridViewCellStyle1.BackColor = System.Drawing.Color.Aqua
        DataGridViewCellStyle1.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.dgWtransmittal.AlternatingRowsDefaultCellStyle = DataGridViewCellStyle1
        Me.dgWtransmittal.AutoSizeRowsMode = System.Windows.Forms.DataGridViewAutoSizeRowsMode.AllCellsExceptHeaders
        Me.dgWtransmittal.BackgroundColor = System.Drawing.Color.White
        Me.dgWtransmittal.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.dgWtransmittal.CellBorderStyle = System.Windows.Forms.DataGridViewCellBorderStyle.SunkenHorizontal
        DataGridViewCellStyle2.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle2.BackColor = System.Drawing.Color.Black
        DataGridViewCellStyle2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle2.ForeColor = System.Drawing.Color.Yellow
        DataGridViewCellStyle2.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle2.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle2.WrapMode = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgWtransmittal.ColumnHeadersDefaultCellStyle = DataGridViewCellStyle2
        Me.dgWtransmittal.ColumnHeadersHeightSizeMode = System.Windows.Forms.DataGridViewColumnHeadersHeightSizeMode.AutoSize
        Me.dgWtransmittal.Columns.AddRange(New System.Windows.Forms.DataGridViewColumn() {Me.Column8, Me.Column1, Me.Column2, Me.Column3, Me.Column5, Me.Column6, Me.Column7, Me.Column9})
        DataGridViewCellStyle3.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle3.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle3.ForeColor = System.Drawing.Color.Black
        DataGridViewCellStyle3.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle3.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle3.WrapMode = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgWtransmittal.DefaultCellStyle = DataGridViewCellStyle3
        Me.dgWtransmittal.EditMode = System.Windows.Forms.DataGridViewEditMode.EditProgrammatically
        Me.dgWtransmittal.ImeMode = System.Windows.Forms.ImeMode.Disable
        Me.dgWtransmittal.Location = New System.Drawing.Point(12, 27)
        Me.dgWtransmittal.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.dgWtransmittal.MultiSelect = False
        Me.dgWtransmittal.Name = "dgWtransmittal"
        Me.dgWtransmittal.ReadOnly = True
        Me.dgWtransmittal.RowHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.Sunken
        DataGridViewCellStyle4.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle4.BackColor = System.Drawing.Color.Black
        DataGridViewCellStyle4.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle4.ForeColor = System.Drawing.Color.Yellow
        DataGridViewCellStyle4.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle4.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        Me.dgWtransmittal.RowHeadersDefaultCellStyle = DataGridViewCellStyle4
        Me.dgWtransmittal.RowHeadersVisible = False
        Me.dgWtransmittal.RowHeadersWidthSizeMode = System.Windows.Forms.DataGridViewRowHeadersWidthSizeMode.DisableResizing
        DataGridViewCellStyle5.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle5.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle5.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle5.ForeColor = System.Drawing.Color.Black
        Me.dgWtransmittal.RowsDefaultCellStyle = DataGridViewCellStyle5
        Me.dgWtransmittal.ScrollBars = System.Windows.Forms.ScrollBars.Vertical
        Me.dgWtransmittal.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.FullRowSelect
        Me.dgWtransmittal.ShowCellErrors = False
        Me.dgWtransmittal.ShowCellToolTips = False
        Me.dgWtransmittal.ShowEditingIcon = False
        Me.dgWtransmittal.ShowRowErrors = False
        Me.dgWtransmittal.Size = New System.Drawing.Size(932, 427)
        Me.dgWtransmittal.TabIndex = 1
        Me.dgWtransmittal.TabStop = False
        '
        'GroupBox3
        '
        Me.GroupBox3.Controls.Add(Me.dgWtransmittal)
        Me.GroupBox3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox3.Location = New System.Drawing.Point(16, 262)
        Me.GroupBox3.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.GroupBox3.Name = "GroupBox3"
        Me.GroupBox3.Padding = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.GroupBox3.Size = New System.Drawing.Size(957, 473)
        Me.GroupBox3.TabIndex = 40
        Me.GroupBox3.TabStop = False
        Me.GroupBox3.Text = "CURRENT CHARGES"
        '
        'GroupBox1
        '
        Me.GroupBox1.Controls.Add(Me.btnEdit)
        Me.GroupBox1.Controls.Add(Me.btnSearchTrans)
        Me.GroupBox1.Controls.Add(Me.btnPrintTrans)
        Me.GroupBox1.Controls.Add(Me.GroupBox5)
        Me.GroupBox1.Controls.Add(Me.btnDelTrans)
        Me.GroupBox1.Controls.Add(Me.btnDelOr)
        Me.GroupBox1.Controls.Add(Me.btnAddOr)
        Me.GroupBox1.Controls.Add(Me.btnNewTrans)
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.Location = New System.Drawing.Point(16, 68)
        Me.GroupBox1.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Padding = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.GroupBox1.Size = New System.Drawing.Size(957, 100)
        Me.GroupBox1.TabIndex = 38
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
        Me.btnEdit.Location = New System.Drawing.Point(616, 18)
        Me.btnEdit.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.btnEdit.Name = "btnEdit"
        Me.btnEdit.Size = New System.Drawing.Size(93, 74)
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
        Me.btnSearchTrans.Location = New System.Drawing.Point(515, 18)
        Me.btnSearchTrans.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.btnSearchTrans.Name = "btnSearchTrans"
        Me.btnSearchTrans.Size = New System.Drawing.Size(93, 74)
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
        Me.btnPrintTrans.Location = New System.Drawing.Point(312, 18)
        Me.btnPrintTrans.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.btnPrintTrans.Name = "btnPrintTrans"
        Me.btnPrintTrans.Size = New System.Drawing.Size(93, 74)
        Me.btnPrintTrans.TabIndex = 32
        Me.btnPrintTrans.TabStop = False
        Me.btnPrintTrans.Text = "F4 PRINT TRANSMIT."
        Me.btnPrintTrans.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnPrintTrans.UseVisualStyleBackColor = False
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
        Me.btnDelTrans.Location = New System.Drawing.Point(413, 18)
        Me.btnDelTrans.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.btnDelTrans.Name = "btnDelTrans"
        Me.btnDelTrans.Size = New System.Drawing.Size(93, 74)
        Me.btnDelTrans.TabIndex = 10
        Me.btnDelTrans.TabStop = False
        Me.btnDelTrans.Text = "F5 DELETE TRANSMIT."
        Me.btnDelTrans.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnDelTrans.UseVisualStyleBackColor = False
        '
        'btnDelOr
        '
        Me.btnDelOr.BackColor = System.Drawing.Color.Transparent
        Me.btnDelOr.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnDelOr.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnDelOr.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnDelOr.Image = Global.billing.My.Resources.Resources.delete_property_26
        Me.btnDelOr.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnDelOr.Location = New System.Drawing.Point(211, 18)
        Me.btnDelOr.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.btnDelOr.Name = "btnDelOr"
        Me.btnDelOr.Size = New System.Drawing.Size(93, 74)
        Me.btnDelOr.TabIndex = 9
        Me.btnDelOr.TabStop = False
        Me.btnDelOr.Text = "F3 DELETE OR"
        Me.btnDelOr.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnDelOr.UseVisualStyleBackColor = False
        '
        'btnAddOr
        '
        Me.btnAddOr.BackColor = System.Drawing.Color.Transparent
        Me.btnAddOr.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnAddOr.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnAddOr.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnAddOr.Image = Global.billing.My.Resources.Resources.add_list_26
        Me.btnAddOr.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnAddOr.Location = New System.Drawing.Point(109, 18)
        Me.btnAddOr.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.btnAddOr.Name = "btnAddOr"
        Me.btnAddOr.Size = New System.Drawing.Size(93, 74)
        Me.btnAddOr.TabIndex = 8
        Me.btnAddOr.TabStop = False
        Me.btnAddOr.Text = "F2 ADD OR"
        Me.btnAddOr.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnAddOr.UseVisualStyleBackColor = False
        '
        'btnNewTrans
        '
        Me.btnNewTrans.BackColor = System.Drawing.Color.Transparent
        Me.btnNewTrans.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnNewTrans.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnNewTrans.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnNewTrans.Image = Global.billing.My.Resources.Resources.google_news_26
        Me.btnNewTrans.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnNewTrans.Location = New System.Drawing.Point(8, 18)
        Me.btnNewTrans.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.btnNewTrans.Name = "btnNewTrans"
        Me.btnNewTrans.Size = New System.Drawing.Size(93, 74)
        Me.btnNewTrans.TabIndex = 7
        Me.btnNewTrans.TabStop = False
        Me.btnNewTrans.Text = "F1 NEW TRANSMIT."
        Me.btnNewTrans.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnNewTrans.UseVisualStyleBackColor = False
        '
        'Column8
        '
        Me.Column8.DataPropertyName = "wi_id"
        Me.Column8.HeaderText = "id"
        Me.Column8.Name = "Column8"
        Me.Column8.ReadOnly = True
        Me.Column8.Visible = False
        Me.Column8.Width = 50
        '
        'Column1
        '
        Me.Column1.DataPropertyName = "wi_ccode"
        Me.Column1.HeaderText = "Code"
        Me.Column1.Name = "Column1"
        Me.Column1.ReadOnly = True
        Me.Column1.Width = 70
        '
        'Column2
        '
        Me.Column2.DataPropertyName = "wi_cname"
        Me.Column2.HeaderText = "Customer's Name"
        Me.Column2.Name = "Column2"
        Me.Column2.ReadOnly = True
        Me.Column2.Width = 200
        '
        'Column3
        '
        Me.Column3.DataPropertyName = "wi_period"
        Me.Column3.HeaderText = "Period"
        Me.Column3.Name = "Column3"
        Me.Column3.ReadOnly = True
        Me.Column3.Width = 60
        '
        'Column5
        '
        Me.Column5.DataPropertyName = "wi_income"
        Me.Column5.HeaderText = "Income Payment"
        Me.Column5.Name = "Column5"
        Me.Column5.ReadOnly = True
        '
        'Column6
        '
        Me.Column6.DataPropertyName = "wi_htax"
        Me.Column6.HeaderText = "WHTAX"
        Me.Column6.Name = "Column6"
        Me.Column6.ReadOnly = True
        '
        'Column7
        '
        Me.Column7.DataPropertyName = "wi_ornum"
        Me.Column7.HeaderText = "OR NO."
        Me.Column7.Name = "Column7"
        Me.Column7.ReadOnly = True
        '
        'Column9
        '
        Me.Column9.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.Column9.DataPropertyName = "wi_date"
        Me.Column9.HeaderText = "DATE"
        Me.Column9.Name = "Column9"
        Me.Column9.ReadOnly = True
        '
        'frmWtrans
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(8.0!, 16.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.BackColor = System.Drawing.Color.White
        Me.ClientSize = New System.Drawing.Size(989, 752)
        Me.ControlBox = False
        Me.Controls.Add(Me.btnClose)
        Me.Controls.Add(Me.txtindi1)
        Me.Controls.Add(Me.Label1)
        Me.Controls.Add(Me.txtindi)
        Me.Controls.Add(Me.GroupBox2)
        Me.Controls.Add(Me.GroupBox3)
        Me.Controls.Add(Me.GroupBox1)
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.None
        Me.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.Name = "frmWtrans"
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterParent
        Me.Text = "frmWtrans"
        Me.GroupBox5.ResumeLayout(False)
        Me.GroupBox5.PerformLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox2.ResumeLayout(False)
        Me.GroupBox2.PerformLayout()
        CType(Me.dgWtransmittal, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox3.ResumeLayout(False)
        Me.GroupBox1.ResumeLayout(False)
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents btnClose As System.Windows.Forms.Button
    Friend WithEvents txtindi1 As System.Windows.Forms.TextBox
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents btnDelTrans As System.Windows.Forms.Button
    Friend WithEvents btnDelOr As System.Windows.Forms.Button
    Friend WithEvents btnAddOr As System.Windows.Forms.Button
    Friend WithEvents btnNewTrans As System.Windows.Forms.Button
    Friend WithEvents txtNotice As System.Windows.Forms.TextBox
    Friend WithEvents PictureBox1 As System.Windows.Forms.PictureBox
    Friend WithEvents btnPrintTrans As System.Windows.Forms.Button
    Friend WithEvents GroupBox5 As System.Windows.Forms.GroupBox
    Friend WithEvents txtBilldate As System.Windows.Forms.DateTimePicker
    Friend WithEvents txtWtransno As System.Windows.Forms.MaskedTextBox
    Friend WithEvents Label3 As System.Windows.Forms.Label
    Friend WithEvents btnEdit As System.Windows.Forms.Button
    Friend WithEvents btnSearchTrans As System.Windows.Forms.Button
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents txtindi As System.Windows.Forms.TextBox
    Friend WithEvents GroupBox2 As System.Windows.Forms.GroupBox
    Friend WithEvents dgWtransmittal As System.Windows.Forms.DataGridView
    Friend WithEvents GroupBox3 As System.Windows.Forms.GroupBox
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents Column8 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column1 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column2 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column3 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column5 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column6 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column7 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column9 As System.Windows.Forms.DataGridViewTextBoxColumn
End Class
