<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmStatement
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
        Me.Label1 = New System.Windows.Forms.Label()
        Me.GroupBox1 = New System.Windows.Forms.GroupBox()
        Me.btnEdit = New System.Windows.Forms.Button()
        Me.btnSearchSoa = New System.Windows.Forms.Button()
        Me.btnPrintSoa = New System.Windows.Forms.Button()
        Me.GroupBox5 = New System.Windows.Forms.GroupBox()
        Me.txtNotice = New System.Windows.Forms.TextBox()
        Me.PictureBox1 = New System.Windows.Forms.PictureBox()
        Me.btnDelSoa = New System.Windows.Forms.Button()
        Me.btnDelBill = New System.Windows.Forms.Button()
        Me.btnAddBill = New System.Windows.Forms.Button()
        Me.btnNewSoa = New System.Windows.Forms.Button()
        Me.txtindi = New System.Windows.Forms.TextBox()
        Me.GroupBox2 = New System.Windows.Forms.GroupBox()
        Me.txtDuedate = New System.Windows.Forms.DateTimePicker()
        Me.txtBilldate = New System.Windows.Forms.DateTimePicker()
        Me.txtTo = New System.Windows.Forms.TextBox()
        Me.txtAddress = New System.Windows.Forms.TextBox()
        Me.Label15 = New System.Windows.Forms.Label()
        Me.Label14 = New System.Windows.Forms.Label()
        Me.txtBal = New System.Windows.Forms.TextBox()
        Me.Label12 = New System.Windows.Forms.Label()
        Me.txtpayment = New System.Windows.Forms.TextBox()
        Me.txtfcharge = New System.Windows.Forms.TextBox()
        Me.txtORpaid = New System.Windows.Forms.TextBox()
        Me.txtOverdue = New System.Windows.Forms.TextBox()
        Me.txtCusname = New System.Windows.Forms.TextBox()
        Me.txtAccno = New System.Windows.Forms.MaskedTextBox()
        Me.Label11 = New System.Windows.Forms.Label()
        Me.Label10 = New System.Windows.Forms.Label()
        Me.txtSoano = New System.Windows.Forms.MaskedTextBox()
        Me.Label16 = New System.Windows.Forms.Label()
        Me.Label7 = New System.Windows.Forms.Label()
        Me.Label6 = New System.Windows.Forms.Label()
        Me.Label5 = New System.Windows.Forms.Label()
        Me.Label4 = New System.Windows.Forms.Label()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.GroupBox3 = New System.Windows.Forms.GroupBox()
        Me.dgStatement = New System.Windows.Forms.DataGridView()
        Me.Column1 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column6 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column2 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column7 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column3 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column4 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column5 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column8 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.GroupBox4 = New System.Windows.Forms.GroupBox()
        Me.txtCharge = New System.Windows.Forms.TextBox()
        Me.Label13 = New System.Windows.Forms.Label()
        Me.txtTotalamtdue = New System.Windows.Forms.TextBox()
        Me.Label9 = New System.Windows.Forms.Label()
        Me.txtindi1 = New System.Windows.Forms.TextBox()
        Me.GroupBox1.SuspendLayout()
        Me.GroupBox5.SuspendLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox2.SuspendLayout()
        Me.GroupBox3.SuspendLayout()
        CType(Me.dgStatement, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.GroupBox4.SuspendLayout()
        Me.SuspendLayout()
        '
        'btnClose
        '
        Me.btnClose.BackColor = System.Drawing.Color.Transparent
        Me.btnClose.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnClose.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnClose.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnClose.Location = New System.Drawing.Point(721, -2)
        Me.btnClose.Name = "btnClose"
        Me.btnClose.Size = New System.Drawing.Size(22, 20)
        Me.btnClose.TabIndex = 7
        Me.btnClose.TabStop = False
        Me.btnClose.Text = "X"
        Me.btnClose.UseVisualStyleBackColor = False
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Font = New System.Drawing.Font("Segoe UI", 15.75!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(224, 11)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(276, 30)
        Me.Label1.TabIndex = 26
        Me.Label1.Text = "BILLING SUMMARY FORM"
        '
        'GroupBox1
        '
        Me.GroupBox1.Controls.Add(Me.btnEdit)
        Me.GroupBox1.Controls.Add(Me.btnSearchSoa)
        Me.GroupBox1.Controls.Add(Me.btnPrintSoa)
        Me.GroupBox1.Controls.Add(Me.GroupBox5)
        Me.GroupBox1.Controls.Add(Me.btnDelSoa)
        Me.GroupBox1.Controls.Add(Me.btnDelBill)
        Me.GroupBox1.Controls.Add(Me.btnAddBill)
        Me.GroupBox1.Controls.Add(Me.btnNewSoa)
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.Location = New System.Drawing.Point(12, 39)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Size = New System.Drawing.Size(718, 81)
        Me.GroupBox1.TabIndex = 27
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
        Me.btnEdit.Text = "F7 EDIT SOA"
        Me.btnEdit.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnEdit.UseVisualStyleBackColor = False
        '
        'btnSearchSoa
        '
        Me.btnSearchSoa.BackColor = System.Drawing.Color.Transparent
        Me.btnSearchSoa.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnSearchSoa.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnSearchSoa.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnSearchSoa.Image = Global.billing.My.Resources.Resources.search_26
        Me.btnSearchSoa.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnSearchSoa.Location = New System.Drawing.Point(386, 15)
        Me.btnSearchSoa.Name = "btnSearchSoa"
        Me.btnSearchSoa.Size = New System.Drawing.Size(70, 60)
        Me.btnSearchSoa.TabIndex = 33
        Me.btnSearchSoa.TabStop = False
        Me.btnSearchSoa.Text = "F6 SEARCH SOA"
        Me.btnSearchSoa.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnSearchSoa.UseVisualStyleBackColor = False
        '
        'btnPrintSoa
        '
        Me.btnPrintSoa.BackColor = System.Drawing.Color.Transparent
        Me.btnPrintSoa.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnPrintSoa.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnPrintSoa.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnPrintSoa.Image = Global.billing.My.Resources.Resources.print_26
        Me.btnPrintSoa.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnPrintSoa.Location = New System.Drawing.Point(234, 15)
        Me.btnPrintSoa.Name = "btnPrintSoa"
        Me.btnPrintSoa.Size = New System.Drawing.Size(70, 60)
        Me.btnPrintSoa.TabIndex = 32
        Me.btnPrintSoa.TabStop = False
        Me.btnPrintSoa.Text = "F4 PRINT SOA"
        Me.btnPrintSoa.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnPrintSoa.UseVisualStyleBackColor = False
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
        'btnDelSoa
        '
        Me.btnDelSoa.BackColor = System.Drawing.Color.Transparent
        Me.btnDelSoa.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnDelSoa.Enabled = False
        Me.btnDelSoa.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnDelSoa.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnDelSoa.Image = Global.billing.My.Resources.Resources.delete_26
        Me.btnDelSoa.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnDelSoa.Location = New System.Drawing.Point(310, 15)
        Me.btnDelSoa.Name = "btnDelSoa"
        Me.btnDelSoa.Size = New System.Drawing.Size(70, 60)
        Me.btnDelSoa.TabIndex = 10
        Me.btnDelSoa.TabStop = False
        Me.btnDelSoa.Text = "F5 DELETE SOA"
        Me.btnDelSoa.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnDelSoa.UseVisualStyleBackColor = False
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
        Me.btnAddBill.Enabled = False
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
        'btnNewSoa
        '
        Me.btnNewSoa.BackColor = System.Drawing.Color.Transparent
        Me.btnNewSoa.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnNewSoa.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnNewSoa.Font = New System.Drawing.Font("Segoe UI", 6.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnNewSoa.Image = Global.billing.My.Resources.Resources.google_news_26
        Me.btnNewSoa.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnNewSoa.Location = New System.Drawing.Point(6, 15)
        Me.btnNewSoa.Name = "btnNewSoa"
        Me.btnNewSoa.Size = New System.Drawing.Size(70, 60)
        Me.btnNewSoa.TabIndex = 7
        Me.btnNewSoa.TabStop = False
        Me.btnNewSoa.Text = "F1 NEW SOA"
        Me.btnNewSoa.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnNewSoa.UseVisualStyleBackColor = False
        '
        'txtindi
        '
        Me.txtindi.Location = New System.Drawing.Point(27, 12)
        Me.txtindi.Name = "txtindi"
        Me.txtindi.Size = New System.Drawing.Size(100, 20)
        Me.txtindi.TabIndex = 32
        Me.txtindi.Text = "1"
        Me.txtindi.Visible = False
        '
        'GroupBox2
        '
        Me.GroupBox2.Controls.Add(Me.txtDuedate)
        Me.GroupBox2.Controls.Add(Me.txtBilldate)
        Me.GroupBox2.Controls.Add(Me.txtTo)
        Me.GroupBox2.Controls.Add(Me.txtAddress)
        Me.GroupBox2.Controls.Add(Me.Label15)
        Me.GroupBox2.Controls.Add(Me.Label14)
        Me.GroupBox2.Controls.Add(Me.txtBal)
        Me.GroupBox2.Controls.Add(Me.Label12)
        Me.GroupBox2.Controls.Add(Me.txtpayment)
        Me.GroupBox2.Controls.Add(Me.txtfcharge)
        Me.GroupBox2.Controls.Add(Me.txtORpaid)
        Me.GroupBox2.Controls.Add(Me.txtOverdue)
        Me.GroupBox2.Controls.Add(Me.txtCusname)
        Me.GroupBox2.Controls.Add(Me.txtAccno)
        Me.GroupBox2.Controls.Add(Me.Label11)
        Me.GroupBox2.Controls.Add(Me.Label10)
        Me.GroupBox2.Controls.Add(Me.txtSoano)
        Me.GroupBox2.Controls.Add(Me.Label16)
        Me.GroupBox2.Controls.Add(Me.Label7)
        Me.GroupBox2.Controls.Add(Me.Label6)
        Me.GroupBox2.Controls.Add(Me.Label5)
        Me.GroupBox2.Controls.Add(Me.Label4)
        Me.GroupBox2.Controls.Add(Me.Label3)
        Me.GroupBox2.Controls.Add(Me.Label2)
        Me.GroupBox2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox2.Location = New System.Drawing.Point(12, 126)
        Me.GroupBox2.Name = "GroupBox2"
        Me.GroupBox2.Size = New System.Drawing.Size(718, 152)
        Me.GroupBox2.TabIndex = 28
        Me.GroupBox2.TabStop = False
        Me.GroupBox2.Text = "INFO"
        '
        'txtDuedate
        '
        Me.txtDuedate.Enabled = False
        Me.txtDuedate.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtDuedate.Format = System.Windows.Forms.DateTimePickerFormat.[Short]
        Me.txtDuedate.Location = New System.Drawing.Point(114, 61)
        Me.txtDuedate.MinDate = New Date(2015, 1, 1, 0, 0, 0, 0)
        Me.txtDuedate.Name = "txtDuedate"
        Me.txtDuedate.Size = New System.Drawing.Size(83, 22)
        Me.txtDuedate.TabIndex = 3
        Me.txtDuedate.Value = New Date(2015, 1, 24, 0, 0, 0, 0)
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
        'txtTo
        '
        Me.txtTo.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtTo.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtTo.BackColor = System.Drawing.Color.White
        Me.txtTo.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtTo.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtTo.Enabled = False
        Me.txtTo.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtTo.ForeColor = System.Drawing.Color.Purple
        Me.txtTo.Location = New System.Drawing.Point(104, 129)
        Me.txtTo.MaxLength = 40
        Me.txtTo.Name = "txtTo"
        Me.txtTo.Size = New System.Drawing.Size(195, 15)
        Me.txtTo.TabIndex = 7
        '
        'txtAddress
        '
        Me.txtAddress.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtAddress.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtAddress.BackColor = System.Drawing.Color.White
        Me.txtAddress.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtAddress.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtAddress.Enabled = False
        Me.txtAddress.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtAddress.ForeColor = System.Drawing.Color.Purple
        Me.txtAddress.Location = New System.Drawing.Point(104, 116)
        Me.txtAddress.MaxLength = 100
        Me.txtAddress.Name = "txtAddress"
        Me.txtAddress.Size = New System.Drawing.Size(195, 15)
        Me.txtAddress.TabIndex = 6
        '
        'Label15
        '
        Me.Label15.AutoSize = True
        Me.Label15.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label15.Location = New System.Drawing.Point(4, 129)
        Me.Label15.Name = "Label15"
        Me.Label15.Size = New System.Drawing.Size(24, 13)
        Me.Label15.TabIndex = 47
        Me.Label15.Text = "TO:"
        '
        'Label14
        '
        Me.Label14.AutoSize = True
        Me.Label14.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label14.Location = New System.Drawing.Point(4, 116)
        Me.Label14.Name = "Label14"
        Me.Label14.Size = New System.Drawing.Size(58, 13)
        Me.Label14.TabIndex = 46
        Me.Label14.Text = "ADDRESS:"
        '
        'txtBal
        '
        Me.txtBal.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtBal.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtBal.BackColor = System.Drawing.Color.White
        Me.txtBal.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtBal.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtBal.Enabled = False
        Me.txtBal.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtBal.ForeColor = System.Drawing.Color.Purple
        Me.txtBal.Location = New System.Drawing.Point(468, 16)
        Me.txtBal.MaxLength = 30
        Me.txtBal.Name = "txtBal"
        Me.txtBal.Size = New System.Drawing.Size(142, 15)
        Me.txtBal.TabIndex = 8
        Me.txtBal.Text = "0.00"
        '
        'Label12
        '
        Me.Label12.AutoSize = True
        Me.Label12.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label12.Location = New System.Drawing.Point(307, 16)
        Me.Label12.Name = "Label12"
        Me.Label12.Size = New System.Drawing.Size(57, 13)
        Me.Label12.TabIndex = 45
        Me.Label12.Text = "BALANCE:"
        '
        'txtpayment
        '
        Me.txtpayment.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtpayment.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtpayment.BackColor = System.Drawing.Color.White
        Me.txtpayment.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtpayment.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtpayment.Enabled = False
        Me.txtpayment.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtpayment.ForeColor = System.Drawing.Color.Purple
        Me.txtpayment.Location = New System.Drawing.Point(468, 42)
        Me.txtpayment.MaxLength = 30
        Me.txtpayment.Name = "txtpayment"
        Me.txtpayment.Size = New System.Drawing.Size(142, 15)
        Me.txtpayment.TabIndex = 10
        Me.txtpayment.Text = "0.00"
        '
        'txtfcharge
        '
        Me.txtfcharge.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtfcharge.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtfcharge.BackColor = System.Drawing.Color.White
        Me.txtfcharge.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtfcharge.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtfcharge.Enabled = False
        Me.txtfcharge.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtfcharge.ForeColor = System.Drawing.Color.Purple
        Me.txtfcharge.Location = New System.Drawing.Point(468, 29)
        Me.txtfcharge.MaxLength = 30
        Me.txtfcharge.Name = "txtfcharge"
        Me.txtfcharge.Size = New System.Drawing.Size(142, 15)
        Me.txtfcharge.TabIndex = 9
        Me.txtfcharge.Text = "0.00"
        '
        'txtORpaid
        '
        Me.txtORpaid.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtORpaid.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtORpaid.BackColor = System.Drawing.Color.White
        Me.txtORpaid.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtORpaid.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtORpaid.Enabled = False
        Me.txtORpaid.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtORpaid.ForeColor = System.Drawing.Color.Maroon
        Me.txtORpaid.Location = New System.Drawing.Point(468, 55)
        Me.txtORpaid.MaxLength = 70
        Me.txtORpaid.Multiline = True
        Me.txtORpaid.Name = "txtORpaid"
        Me.txtORpaid.Size = New System.Drawing.Size(204, 55)
        Me.txtORpaid.TabIndex = 11
        '
        'txtOverdue
        '
        Me.txtOverdue.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtOverdue.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtOverdue.BackColor = System.Drawing.Color.White
        Me.txtOverdue.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtOverdue.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtOverdue.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtOverdue.ForeColor = System.Drawing.Color.Red
        Me.txtOverdue.Location = New System.Drawing.Point(468, 116)
        Me.txtOverdue.MaxLength = 30
        Me.txtOverdue.Name = "txtOverdue"
        Me.txtOverdue.ReadOnly = True
        Me.txtOverdue.Size = New System.Drawing.Size(142, 15)
        Me.txtOverdue.TabIndex = 44
        Me.txtOverdue.TabStop = False
        Me.txtOverdue.Text = "0.00"
        '
        'txtCusname
        '
        Me.txtCusname.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtCusname.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtCusname.BackColor = System.Drawing.Color.White
        Me.txtCusname.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtCusname.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtCusname.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtCusname.ForeColor = System.Drawing.Color.Purple
        Me.txtCusname.Location = New System.Drawing.Point(104, 103)
        Me.txtCusname.MaxLength = 30
        Me.txtCusname.Name = "txtCusname"
        Me.txtCusname.ReadOnly = True
        Me.txtCusname.Size = New System.Drawing.Size(198, 15)
        Me.txtCusname.TabIndex = 5
        Me.txtCusname.TabStop = False
        '
        'txtAccno
        '
        Me.txtAccno.BackColor = System.Drawing.Color.White
        Me.txtAccno.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtAccno.Enabled = False
        Me.txtAccno.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtAccno.ForeColor = System.Drawing.Color.Purple
        Me.txtAccno.Location = New System.Drawing.Point(104, 90)
        Me.txtAccno.Mask = "#####-####"
        Me.txtAccno.Name = "txtAccno"
        Me.txtAccno.Size = New System.Drawing.Size(91, 15)
        Me.txtAccno.TabIndex = 4
        Me.txtAccno.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label11
        '
        Me.Label11.AutoSize = True
        Me.Label11.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label11.Location = New System.Drawing.Point(307, 29)
        Me.Label11.Name = "Label11"
        Me.Label11.Size = New System.Drawing.Size(107, 13)
        Me.Label11.TabIndex = 36
        Me.Label11.Text = "FINANCE CHARGES:"
        '
        'Label10
        '
        Me.Label10.AutoSize = True
        Me.Label10.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label10.Location = New System.Drawing.Point(4, 103)
        Me.Label10.Name = "Label10"
        Me.Label10.Size = New System.Drawing.Size(68, 13)
        Me.Label10.TabIndex = 35
        Me.Label10.Text = "CUSTOMER:"
        '
        'txtSoano
        '
        Me.txtSoano.BackColor = System.Drawing.Color.White
        Me.txtSoano.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtSoano.Enabled = False
        Me.txtSoano.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtSoano.ForeColor = System.Drawing.Color.Purple
        Me.txtSoano.Location = New System.Drawing.Point(106, 16)
        Me.txtSoano.Mask = "#####"
        Me.txtSoano.Name = "txtSoano"
        Me.txtSoano.Size = New System.Drawing.Size(91, 15)
        Me.txtSoano.TabIndex = 1
        Me.txtSoano.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label16
        '
        Me.Label16.AutoSize = True
        Me.Label16.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label16.Location = New System.Drawing.Point(307, 116)
        Me.Label16.Name = "Label16"
        Me.Label16.Size = New System.Drawing.Size(150, 13)
        Me.Label16.TabIndex = 33
        Me.Label16.Text = "TOTAL OVER DUE CHARGES:"
        '
        'Label7
        '
        Me.Label7.AutoSize = True
        Me.Label7.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label7.Location = New System.Drawing.Point(307, 55)
        Me.Label7.Name = "Label7"
        Me.Label7.Size = New System.Drawing.Size(60, 13)
        Me.Label7.TabIndex = 31
        Me.Label7.Text = "OR# PAID:"
        '
        'Label6
        '
        Me.Label6.AutoSize = True
        Me.Label6.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label6.Location = New System.Drawing.Point(4, 90)
        Me.Label6.Name = "Label6"
        Me.Label6.Size = New System.Drawing.Size(71, 13)
        Me.Label6.TabIndex = 30
        Me.Label6.Text = "ACCOUNT #:"
        '
        'Label5
        '
        Me.Label5.AutoSize = True
        Me.Label5.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label5.Location = New System.Drawing.Point(307, 42)
        Me.Label5.Name = "Label5"
        Me.Label5.Size = New System.Drawing.Size(57, 13)
        Me.Label5.TabIndex = 29
        Me.Label5.Text = "PAYMENT:"
        '
        'Label4
        '
        Me.Label4.AutoSize = True
        Me.Label4.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label4.Location = New System.Drawing.Point(4, 67)
        Me.Label4.Name = "Label4"
        Me.Label4.Size = New System.Drawing.Size(61, 13)
        Me.Label4.TabIndex = 28
        Me.Label4.Text = "DUE DATE:"
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.Location = New System.Drawing.Point(4, 41)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(78, 13)
        Me.Label3.TabIndex = 27
        Me.Label3.Text = "BILLING DATE:"
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(6, 16)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(42, 13)
        Me.Label2.TabIndex = 26
        Me.Label2.Text = "SOA #:"
        '
        'GroupBox3
        '
        Me.GroupBox3.Controls.Add(Me.dgStatement)
        Me.GroupBox3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox3.Location = New System.Drawing.Point(12, 284)
        Me.GroupBox3.Name = "GroupBox3"
        Me.GroupBox3.Size = New System.Drawing.Size(718, 244)
        Me.GroupBox3.TabIndex = 29
        Me.GroupBox3.TabStop = False
        Me.GroupBox3.Text = "CURRENT CHARGES"
        '
        'dgStatement
        '
        Me.dgStatement.AllowUserToAddRows = False
        Me.dgStatement.AllowUserToDeleteRows = False
        Me.dgStatement.AllowUserToResizeColumns = False
        Me.dgStatement.AllowUserToResizeRows = False
        DataGridViewCellStyle1.BackColor = System.Drawing.Color.Aqua
        DataGridViewCellStyle1.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.dgStatement.AlternatingRowsDefaultCellStyle = DataGridViewCellStyle1
        Me.dgStatement.AutoSizeRowsMode = System.Windows.Forms.DataGridViewAutoSizeRowsMode.AllCellsExceptHeaders
        Me.dgStatement.BackgroundColor = System.Drawing.Color.White
        Me.dgStatement.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.dgStatement.CellBorderStyle = System.Windows.Forms.DataGridViewCellBorderStyle.SunkenHorizontal
        DataGridViewCellStyle2.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle2.BackColor = System.Drawing.Color.Black
        DataGridViewCellStyle2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle2.ForeColor = System.Drawing.Color.Yellow
        DataGridViewCellStyle2.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle2.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle2.WrapMode = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgStatement.ColumnHeadersDefaultCellStyle = DataGridViewCellStyle2
        Me.dgStatement.ColumnHeadersHeightSizeMode = System.Windows.Forms.DataGridViewColumnHeadersHeightSizeMode.AutoSize
        Me.dgStatement.Columns.AddRange(New System.Windows.Forms.DataGridViewColumn() {Me.Column1, Me.Column6, Me.Column2, Me.Column7, Me.Column3, Me.Column4, Me.Column5, Me.Column8})
        DataGridViewCellStyle3.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle3.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle3.ForeColor = System.Drawing.Color.Black
        DataGridViewCellStyle3.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle3.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle3.WrapMode = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgStatement.DefaultCellStyle = DataGridViewCellStyle3
        Me.dgStatement.EditMode = System.Windows.Forms.DataGridViewEditMode.EditProgrammatically
        Me.dgStatement.ImeMode = System.Windows.Forms.ImeMode.Disable
        Me.dgStatement.Location = New System.Drawing.Point(9, 22)
        Me.dgStatement.MultiSelect = False
        Me.dgStatement.Name = "dgStatement"
        Me.dgStatement.ReadOnly = True
        Me.dgStatement.RowHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.Sunken
        DataGridViewCellStyle4.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle4.BackColor = System.Drawing.Color.Black
        DataGridViewCellStyle4.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle4.ForeColor = System.Drawing.Color.Yellow
        DataGridViewCellStyle4.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle4.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        Me.dgStatement.RowHeadersDefaultCellStyle = DataGridViewCellStyle4
        Me.dgStatement.RowHeadersVisible = False
        Me.dgStatement.RowHeadersWidthSizeMode = System.Windows.Forms.DataGridViewRowHeadersWidthSizeMode.DisableResizing
        DataGridViewCellStyle5.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle5.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle5.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle5.ForeColor = System.Drawing.Color.Black
        Me.dgStatement.RowsDefaultCellStyle = DataGridViewCellStyle5
        Me.dgStatement.ScrollBars = System.Windows.Forms.ScrollBars.Vertical
        Me.dgStatement.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.FullRowSelect
        Me.dgStatement.ShowCellErrors = False
        Me.dgStatement.ShowCellToolTips = False
        Me.dgStatement.ShowEditingIcon = False
        Me.dgStatement.ShowRowErrors = False
        Me.dgStatement.Size = New System.Drawing.Size(699, 209)
        Me.dgStatement.TabIndex = 1
        Me.dgStatement.TabStop = False
        '
        'Column1
        '
        Me.Column1.DataPropertyName = "si_billdate"
        Me.Column1.HeaderText = "DATE"
        Me.Column1.Name = "Column1"
        Me.Column1.ReadOnly = True
        Me.Column1.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column1.Width = 80
        '
        'Column6
        '
        Me.Column6.DataPropertyName = "si_billnum"
        Me.Column6.HeaderText = "BILL #"
        Me.Column6.Name = "Column6"
        Me.Column6.ReadOnly = True
        Me.Column6.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column6.Width = 50
        '
        'Column2
        '
        Me.Column2.DataPropertyName = "si_vessel"
        Me.Column2.HeaderText = "VESSEL"
        Me.Column2.Name = "Column2"
        Me.Column2.ReadOnly = True
        Me.Column2.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column2.Width = 120
        '
        'Column7
        '
        Me.Column7.DataPropertyName = "si_ponum"
        Me.Column7.HeaderText = "P.O.#"
        Me.Column7.Name = "Column7"
        Me.Column7.ReadOnly = True
        '
        'Column3
        '
        Me.Column3.DataPropertyName = "si_voyage"
        Me.Column3.HeaderText = "VOYAGE"
        Me.Column3.Name = "Column3"
        Me.Column3.ReadOnly = True
        Me.Column3.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column3.Width = 70
        '
        'Column4
        '
        Me.Column4.DataPropertyName = "si_service"
        Me.Column4.HeaderText = "SERVICE RENDERED"
        Me.Column4.Name = "Column4"
        Me.Column4.ReadOnly = True
        Me.Column4.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.Column4.Width = 200
        '
        'Column5
        '
        Me.Column5.DataPropertyName = "si_scipsi"
        Me.Column5.HeaderText = "AMOUNT"
        Me.Column5.Name = "Column5"
        Me.Column5.ReadOnly = True
        Me.Column5.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        '
        'Column8
        '
        Me.Column8.DataPropertyName = "id_soa_item"
        Me.Column8.HeaderText = "id"
        Me.Column8.Name = "Column8"
        Me.Column8.ReadOnly = True
        Me.Column8.Visible = False
        '
        'GroupBox4
        '
        Me.GroupBox4.Controls.Add(Me.txtCharge)
        Me.GroupBox4.Controls.Add(Me.Label13)
        Me.GroupBox4.Controls.Add(Me.txtTotalamtdue)
        Me.GroupBox4.Controls.Add(Me.Label9)
        Me.GroupBox4.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox4.Location = New System.Drawing.Point(12, 534)
        Me.GroupBox4.Name = "GroupBox4"
        Me.GroupBox4.Size = New System.Drawing.Size(718, 65)
        Me.GroupBox4.TabIndex = 31
        Me.GroupBox4.TabStop = False
        Me.GroupBox4.Text = "AMOUNT"
        '
        'txtCharge
        '
        Me.txtCharge.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtCharge.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtCharge.BackColor = System.Drawing.Color.White
        Me.txtCharge.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtCharge.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtCharge.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtCharge.ForeColor = System.Drawing.Color.Red
        Me.txtCharge.Location = New System.Drawing.Point(517, 13)
        Me.txtCharge.MaxLength = 30
        Me.txtCharge.Name = "txtCharge"
        Me.txtCharge.ReadOnly = True
        Me.txtCharge.Size = New System.Drawing.Size(183, 22)
        Me.txtCharge.TabIndex = 46
        Me.txtCharge.TabStop = False
        Me.txtCharge.Text = "0.00"
        Me.txtCharge.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label13
        '
        Me.Label13.AutoSize = True
        Me.Label13.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label13.Location = New System.Drawing.Point(7, 13)
        Me.Label13.Name = "Label13"
        Me.Label13.Size = New System.Drawing.Size(163, 21)
        Me.Label13.TabIndex = 45
        Me.Label13.Text = "CURRENT CHARGES:"
        '
        'txtTotalamtdue
        '
        Me.txtTotalamtdue.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtTotalamtdue.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtTotalamtdue.BackColor = System.Drawing.Color.White
        Me.txtTotalamtdue.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtTotalamtdue.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtTotalamtdue.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtTotalamtdue.ForeColor = System.Drawing.Color.Red
        Me.txtTotalamtdue.Location = New System.Drawing.Point(517, 38)
        Me.txtTotalamtdue.MaxLength = 30
        Me.txtTotalamtdue.Name = "txtTotalamtdue"
        Me.txtTotalamtdue.ReadOnly = True
        Me.txtTotalamtdue.Size = New System.Drawing.Size(183, 22)
        Me.txtTotalamtdue.TabIndex = 44
        Me.txtTotalamtdue.TabStop = False
        Me.txtTotalamtdue.Text = "0.00"
        Me.txtTotalamtdue.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label9
        '
        Me.Label9.AutoSize = True
        Me.Label9.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label9.Location = New System.Drawing.Point(8, 38)
        Me.Label9.Name = "Label9"
        Me.Label9.Size = New System.Drawing.Size(173, 21)
        Me.Label9.TabIndex = 31
        Me.Label9.Text = "TOTAL AMOUNT DUE:"
        '
        'txtindi1
        '
        Me.txtindi1.Location = New System.Drawing.Point(133, 11)
        Me.txtindi1.Name = "txtindi1"
        Me.txtindi1.Size = New System.Drawing.Size(100, 20)
        Me.txtindi1.TabIndex = 33
        Me.txtindi1.Text = "0"
        Me.txtindi1.Visible = False
        '
        'frmStatement
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(6.0!, 13.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.BackColor = System.Drawing.Color.White
        Me.ClientSize = New System.Drawing.Size(742, 611)
        Me.ControlBox = False
        Me.Controls.Add(Me.txtindi1)
        Me.Controls.Add(Me.txtindi)
        Me.Controls.Add(Me.GroupBox4)
        Me.Controls.Add(Me.GroupBox3)
        Me.Controls.Add(Me.GroupBox2)
        Me.Controls.Add(Me.GroupBox1)
        Me.Controls.Add(Me.Label1)
        Me.Controls.Add(Me.btnClose)
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.None
        Me.MaximizeBox = False
        Me.MinimizeBox = False
        Me.Name = "frmStatement"
        Me.ShowIcon = False
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterParent
        Me.Text = "frmStatement"
        Me.GroupBox1.ResumeLayout(False)
        Me.GroupBox5.ResumeLayout(False)
        Me.GroupBox5.PerformLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox2.ResumeLayout(False)
        Me.GroupBox2.PerformLayout()
        Me.GroupBox3.ResumeLayout(False)
        CType(Me.dgStatement, System.ComponentModel.ISupportInitialize).EndInit()
        Me.GroupBox4.ResumeLayout(False)
        Me.GroupBox4.PerformLayout()
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents btnClose As System.Windows.Forms.Button
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents btnNewSoa As System.Windows.Forms.Button
    Friend WithEvents btnDelSoa As System.Windows.Forms.Button
    Friend WithEvents btnDelBill As System.Windows.Forms.Button
    Friend WithEvents btnAddBill As System.Windows.Forms.Button
    Friend WithEvents GroupBox5 As System.Windows.Forms.GroupBox
    Friend WithEvents txtNotice As System.Windows.Forms.TextBox
    Friend WithEvents PictureBox1 As System.Windows.Forms.PictureBox
    Friend WithEvents GroupBox2 As System.Windows.Forms.GroupBox
    Friend WithEvents Label4 As System.Windows.Forms.Label
    Friend WithEvents Label3 As System.Windows.Forms.Label
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents Label5 As System.Windows.Forms.Label
    Friend WithEvents btnPrintSoa As System.Windows.Forms.Button
    Friend WithEvents Label7 As System.Windows.Forms.Label
    Friend WithEvents Label6 As System.Windows.Forms.Label
    Friend WithEvents Label16 As System.Windows.Forms.Label
    Friend WithEvents GroupBox3 As System.Windows.Forms.GroupBox
    Friend WithEvents GroupBox4 As System.Windows.Forms.GroupBox
    Friend WithEvents Label9 As System.Windows.Forms.Label
    Friend WithEvents btnSearchSoa As System.Windows.Forms.Button
    Friend WithEvents dgStatement As System.Windows.Forms.DataGridView
    Friend WithEvents Label11 As System.Windows.Forms.Label
    Friend WithEvents Label10 As System.Windows.Forms.Label
    Friend WithEvents txtSoano As System.Windows.Forms.MaskedTextBox
    Friend WithEvents txtAccno As System.Windows.Forms.MaskedTextBox
    Friend WithEvents txtOverdue As System.Windows.Forms.TextBox
    Friend WithEvents txtCusname As System.Windows.Forms.TextBox
    Friend WithEvents txtTotalamtdue As System.Windows.Forms.TextBox
    Friend WithEvents txtORpaid As System.Windows.Forms.TextBox
    Friend WithEvents txtpayment As System.Windows.Forms.TextBox
    Friend WithEvents txtfcharge As System.Windows.Forms.TextBox
    Friend WithEvents txtBal As System.Windows.Forms.TextBox
    Friend WithEvents Label12 As System.Windows.Forms.Label
    Friend WithEvents txtCharge As System.Windows.Forms.TextBox
    Friend WithEvents Label13 As System.Windows.Forms.Label
    Friend WithEvents txtTo As System.Windows.Forms.TextBox
    Friend WithEvents txtAddress As System.Windows.Forms.TextBox
    Friend WithEvents Label15 As System.Windows.Forms.Label
    Friend WithEvents Label14 As System.Windows.Forms.Label
    Friend WithEvents txtindi As System.Windows.Forms.TextBox
    Friend WithEvents txtBilldate As System.Windows.Forms.DateTimePicker
    Friend WithEvents txtDuedate As System.Windows.Forms.DateTimePicker
    Friend WithEvents Column1 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column6 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column2 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column7 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column3 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column4 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column5 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column8 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents btnEdit As System.Windows.Forms.Button
    Friend WithEvents txtindi1 As System.Windows.Forms.TextBox
End Class
